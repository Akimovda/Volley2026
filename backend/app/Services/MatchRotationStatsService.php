<?php

namespace App\Services;

use App\Models\MatchLineupEvent;
use App\Models\MatchRallyEvent;
use App\Models\TournamentMatch;
use Illuminate\Support\Collection;

/**
 * Статистика по расстановкам (блок C): по каждой команде и каждой «Р1…РN» — розыгрыши, break point % (на своей подаче),
 * side-out % (на приёме), +/–, эйсы, пропущенные эйсы, ошибки команды, подающий.
 *
 * Расстановка определяется (на момент начала розыгрыша):
 *  • основной способ — по зоне связующего: «Рk» = связующий (setter) в зоне k (с учётом замен, либеро, rotate/swap);
 *  • запасной — по номеру поворота от стартовой расстановки партии: «Рk» = (rot mod N)+1 (если связующего на площадке нет
 *    или он не один, N ≠ 6, нет амплуа). Способ выбирается на команду на весь матч.
 * Источник — тот же реплей, что у пульта: стартовые составы + lineup-события (MatchLineupService::applyEvent) + очки по seq;
 * поворот = side-out каждой команды + ручные rotate. Новых таблиц нет.
 */
class MatchRotationStatsService
{
    public const MIN_RECV_FOR_INSIGHT = 5;

    public function __construct(private MatchLineupService $lineup, private JerseyNumberService $numbers) {}

    /**
     * @param Collection $events        MatchRallyEvent матча
     * @param Collection $lineupEvents  MatchLineupEvent матча
     * @param Collection $setLineups    MatchSetLineup матча
     * @param Collection $starts        MatchSetStart матча (keyBy set_number)
     * @param array<int,string> $names  user_id => «Фамилия Имя»
     * @return array|null  null — учёт состава не велся
     */
    public function build(TournamentMatch $match, Collection $events, Collection $lineupEvents, Collection $setLineups, Collection $starts, array $names): ?array
    {
        $profile = $this->lineup->profile($match);
        if (!$profile['enabled'] || $starts->isEmpty()) {
            return null;
        }

        $size = (int) $profile['on_court_size'];
        $home = (int) $match->team_home_id;
        $away = (int) $match->team_away_id;
        $sideOf = [$home => 'home', $away => 'away'];

        // реплей по партиям: на каждый розыгрыш — расстановки обеих команд на МОМЕНТ НАЧАЛА розыгрыша
        $rallies = [];   // [side => list of [set, setter_k, rot_k, serving, won, action, server_uid]]
        $sets = [];
        foreach ($starts->sortKeys() as $setNumber => $start) {
            if (!$start->first_serve_team_id) {
                continue;
            }
            $sn = (int) $setNumber;
            $teams = MatchLineupService::initialTeams($setLineups->where('set_number', $sn), [$home, $away]);
            $rot = [$home => 0, $away => 0];
            $serving = (int) $start->first_serve_team_id;

            $items = $events->where('set_number', $sn)->map(fn ($e) => ['k' => 'p', 'seq' => (int) $e->seq, 'e' => $e])
                ->concat($lineupEvents->where('set_number', $sn)->map(fn ($e) => ['k' => 'l', 'seq' => (int) $e->seq, 'e' => $e]))
                ->sortBy('seq');

            $sets[] = $sn;
            foreach ($items as $it) {
                $ev = $it['e'];
                if ($it['k'] === 'l') {
                    $tid = (int) $ev->team_id;
                    if ($ev->type === MatchLineupEvent::TYPE_ROTATE) {
                        $rot[$tid] = ($rot[$tid] ?? 0) + (int) $ev->position;
                    } elseif (isset($teams[$tid])) {
                        MatchLineupService::applyEvent($teams[$tid], $ev);
                    }
                    continue;
                }

                $scorer = (int) $ev->team_id;
                foreach ([$home, $away] as $tid) {
                    $t = $teams[$tid];
                    $rk = (($rot[$tid] % max(1, $size)) + max(1, $size)) % max(1, $size) + 1;

                    // связующий: игрок с амплуа setter среди основных (в т.ч. замещённых либеро) — ровно один
                    $setterSlots = [];
                    foreach ($t['court'] as $uid => $c) {
                        if (empty($c['libero']) && $c['position'] === 'setter' && $c['zone'] !== null) {
                            $setterSlots[] = (int) $c['zone'];
                        }
                    }
                    foreach ($t['held'] as $uid => $c) {
                        if (($c['position'] ?? null) === 'setter' && ($c['zone'] ?? null) !== null) {
                            $setterSlots[] = (int) $c['zone'];
                        }
                    }
                    $sk = ($size === 6 && count($setterSlots) === 1) ? MatchLineupService::physicalZone($setterSlots[0], $rot[$tid], $size) : null;

                    $serverUid = null;
                    if ($serving === $tid) {
                        foreach ($t['court'] as $uid => $c) {
                            if (empty($c['libero']) && $c['zone'] !== null && MatchLineupService::physicalZone((int) $c['zone'], $rot[$tid], $size) === 1) {
                                $serverUid = (int) $uid;
                            }
                        }
                    }

                    $rallies[$sideOf[$tid]][] = [
                        'set' => $sn, 'setter_k' => $sk, 'rot_k' => $rk, 'serving' => $serving === $tid,
                        'won' => $scorer === $tid, 'action' => $ev->action_type, 'server' => $serverUid,
                    ];
                }

                if ($scorer !== $serving) {
                    $rot[$scorer] = ($rot[$scorer] ?? 0) + 1;
                    $serving = $scorer;
                }
            }
        }

        if (!$sets) {
            return null;
        }

        // способ на команду: по связующему, только если он однозначен в каждом розыгрыше матча
        $method = [];
        foreach (['home', 'away'] as $side) {
            $list = $rallies[$side] ?? [];
            $method[$side] = ($size === 6 && $list && !collect($list)->contains(fn ($r) => $r['setter_k'] === null)) ? 'setter' : 'rotation';
        }

        $numbers = $this->numbers->forEvent((int) ($match->stage?->event_id ?? 0));
        $out = ['size' => $size, 'sets' => $sets, 'method' => $method, 'teams' => []];
        foreach (['home', 'away'] as $side) {
            $scopes = [];
            foreach (array_merge(['all'], $sets) as $scope) {
                $list = collect($rallies[$side] ?? [])->filter(fn ($r) => $scope === 'all' || $r['set'] === (int) $scope);
                $rows = [];
                for ($k = 1; $k <= $size; $k++) {
                    $rows[$k] = $this->emptyRow($k);
                }
                foreach ($list as $r) {
                    $k = $method[$side] === 'setter' ? $r['setter_k'] : $r['rot_k'];
                    $x = &$rows[$k];
                    $x['plays']++;
                    $x['diff'] += $r['won'] ? 1 : -1;
                    if ($r['serving']) {
                        $x['serve_plays']++;
                        $x['serve_won'] += $r['won'] ? 1 : 0;
                        if ($r['server']) {
                            $x['servers'][$r['server']] = ($x['servers'][$r['server']] ?? 0) + 1;
                        }
                    } else {
                        $x['recv_plays']++;
                        $x['recv_won'] += $r['won'] ? 1 : 0;
                    }
                    if ($r['action'] === MatchRallyEvent::ACTION_ACE) {
                        if ($r['won']) {
                            $x['aces']++;
                        } else {
                            $x['missed_aces']++;
                        }
                    }
                    // ошибка команды = очко соперника по одной из opp_*-ошибок (включая ошибки расстановки)
                    if (!$r['won'] && in_array($r['action'], MatchRallyEvent::OPP_ERROR_ACTIONS, true)) {
                        $x['errors']++;
                    }
                    unset($x);
                }
                foreach ($rows as $k => &$row) {
                    $row['serve_pct'] = $row['serve_plays'] ? (int) round($row['serve_won'] / $row['serve_plays'] * 100) : null;
                    $row['recv_pct'] = $row['recv_plays'] ? (int) round($row['recv_won'] / $row['recv_plays'] * 100) : null;
                    arsort($row['servers']);
                    $fmt = fn (int $uid) => trim((isset($numbers[$uid]) ? '№' . $numbers[$uid] . ' ' : '') . ($names[$uid] ?? ('#' . $uid)));
                    $ids = array_keys($row['servers']);
                    $row['server'] = $ids ? $fmt($ids[0]) : null;
                    $row['servers_other'] = array_map(fn ($uid) => $fmt($uid) . ' ×' . $row['servers'][$uid], array_slice($ids, 1));
                    unset($row['servers']);
                }
                unset($row);

                // подсветка: лучшая/худшая расстановка по side-out % (≥ 5 розыгрышей на приёме у обеих)
                $q = collect($rows)->filter(fn ($r) => $r['recv_plays'] >= self::MIN_RECV_FOR_INSIGHT);
                $insight = ['weak' => null, 'strong' => null];
                if ($q->count() >= 2) {
                    $weak = $q->sortBy([['recv_pct', 'asc'], ['recv_plays', 'desc']])->first();
                    $strong = $q->sortBy([['recv_pct', 'desc'], ['recv_plays', 'desc']])->first();
                    if ($weak['recv_pct'] !== $strong['recv_pct']) {
                        $insight['weak'] = ['k' => $weak['k'], 'pct' => $weak['recv_pct'], 'won' => $weak['recv_won'], 'plays' => $weak['recv_plays']];
                        $insight['strong'] = ['k' => $strong['k'], 'pct' => $strong['recv_pct'], 'won' => $strong['recv_won'], 'plays' => $strong['recv_plays']];
                    }
                }
                $scopes[(string) $scope] = ['rows' => array_values($rows), 'insight' => $insight];
            }
            $out['teams'][$side] = ['scopes' => $scopes];
        }

        return $out;
    }

    private function emptyRow(int $k): array
    {
        return ['k' => $k, 'plays' => 0, 'serve_plays' => 0, 'serve_won' => 0, 'recv_plays' => 0, 'recv_won' => 0,
            'diff' => 0, 'aces' => 0, 'missed_aces' => 0, 'errors' => 0, 'servers' => []];
    }
}
