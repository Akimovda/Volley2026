<?php

namespace App\Services;

use App\Models\MatchLineupEvent;
use App\Models\MatchRallyEvent;
use App\Models\MatchServeMark;
use App\Models\MatchSetLineup;
use App\Models\MatchSetStart;
use App\Models\TournamentMatch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Расширенная статистика матча поверх match_rally_events (+ отметки подачи и состав).
 * Только чтение. Для завершённых матчей результат кэшируется по (match_id, last_seq, updated_at).
 *
 * Подающая команда в розыгрыше. Правило side-out: подаёт победитель предыдущего розыгрыша, поэтому
 * для розыгрыша №2 и дальше подающий известен БЕЗ первого подающего. Первый подающий нужен только для
 * розыгрыша №1: берётся из match_set_starts / match_serve_marks, иначе из «якоря» в самом первом
 * розыгрыше (ace — подавал победитель, opp_serve_error — подавал проигравший). Нет — розыгрыш №1
 * в подсчёт подачи/приёма не входит.
 * «Якоря» (ace / opp_serve_error) — проверка side-out: сет надёжен, только если ВСЕ якоря сета
 * согласуются с ним и есть хотя бы одно подтверждение (якорь или отметка первого подающего).
 */
class MatchAdvancedStatsService
{
    private const CACHE_VERSION = 'v3';

    /** Версия шаблона PDF — повышать при правке matches/stats_pdf и его partial'ов (кэш PDF на диске). */
    private const PDF_TEMPLATE_VERSION = 't4';

    public const ERROR_ACTIONS = [
        MatchRallyEvent::ACTION_OPP_SERVE_ERROR,
        MatchRallyEvent::ACTION_OPP_ATTACK_ERROR,
        MatchRallyEvent::ACTION_OPP_RECEPTION_ERROR,
        MatchRallyEvent::ACTION_OPP_BLOCK_ERROR,
    ];

    public function __construct(private readonly MatchLineupService $lineupService)
    {
    }

    public function hasRally(int $matchId): bool
    {
        return MatchRallyEvent::where('match_id', $matchId)->exists();
    }

    public function build(TournamentMatch $match): array
    {
        if ($match->status !== TournamentMatch::STATUS_COMPLETED) {
            return $this->compute($match);
        }

        $marksTs = MatchServeMark::where('match_id', $match->id)->max('updated_at');
        $key = implode(':', ['match_adv_stats', self::CACHE_VERSION, $match->id, (int) $match->last_seq,
            $match->updated_at?->timestamp ?? 0, $marksTs ? strtotime((string) $marksTs) : 0, app()->getLocale()]);

        return Cache::remember($key, now()->addDays(14), fn () => $this->compute($match));
    }

    /** Ключ версии данных матча — для кэша PDF. */
    public function dataVersion(TournamentMatch $match): string
    {
        $marksTs = MatchServeMark::where('match_id', $match->id)->max('updated_at');

        return implode('-', [self::PDF_TEMPLATE_VERSION, (int) $match->last_seq, $match->updated_at?->timestamp ?? 0, $marksTs ? strtotime((string) $marksTs) : 0]);
    }

    private function compute(TournamentMatch $match): array
    {
        $match->loadMissing(['teamHome', 'teamAway', 'stage.event.location', 'courtModel']);
        $homeId = (int) $match->team_home_id;
        $awayId = (int) $match->team_away_id;
        $sideOf = fn (?int $teamId): ?string => $teamId === null ? null : ((int) $teamId === $homeId ? 'home' : ((int) $teamId === $awayId ? 'away' : null));

        $events = MatchRallyEvent::where('match_id', $match->id)->orderBy('set_number')->orderBy('seq')->get();
        $lineupEvents = MatchLineupEvent::where('match_id', $match->id)->orderBy('set_number')->orderBy('seq')->get();
        $setLineups = MatchSetLineup::where('match_id', $match->id)->get();
        $starts = MatchSetStart::where('match_id', $match->id)->get()->keyBy('set_number');
        $marks = MatchServeMark::where('match_id', $match->id)->get()->keyBy('set_number');
        $roster = $this->lineupService->roster($match);

        $names = $this->resolveNames(
            $events->pluck('player_id')->merge($lineupEvents->pluck('in_user_id'))->merge($lineupEvents->pluck('out_user_id'))
                ->merge($setLineups->pluck('user_id'))
                ->merge(collect($roster)->flatMap(fn ($r) => array_keys($r)))
                ->filter()->unique()->values()
        );

        $setNumbers = $events->pluck('set_number')->unique()->sort()->values()->all();
        $eventsBySet = $events->groupBy('set_number');
        $lineupBySet = $lineupEvents->groupBy('set_number');

        // ---- розыгрыши по сетам: счёт, подача, серии, ход сета ----
        $sets = [];
        $serveTotals = ['home' => ['serve_total' => 0, 'serve_won' => 0, 'recv_total' => 0, 'recv_won' => 0],
                        'away' => ['serve_total' => 0, 'serve_won' => 0, 'recv_total' => 0, 'recv_won' => 0]];
        $setsReliable = 0;

        foreach ($setNumbers as $sn) {
            $setEvents = $eventsBySet->get($sn, collect())->values();
            $serve = $this->analyseServe(
                $setEvents, $homeId, $awayId,
                $starts->get($sn)?->first_serve_team_id ? (int) $starts->get($sn)->first_serve_team_id : null,
                $marks->get($sn)?->team_id ? (int) $marks->get($sn)->team_id : null,
            );
            if ($serve['reliable']) {
                $setsReliable++;
            }

            $h = 0;
            $a = 0;
            $rallies = [];
            $diff = [0];
            $maxLead = ['home' => ['lead' => 0, 'rally' => 0, 'home' => 0, 'away' => 0], 'away' => ['lead' => 0, 'rally' => 0, 'home' => 0, 'away' => 0]];
            $runSide = null;
            $runLen = 0;
            $runFrom = [0, 0];
            $bestRun = ['home' => null, 'away' => null];

            foreach ($setEvents as $i => $ev) {
                $rn = $i + 1;
                $winSide = $sideOf((int) $ev->team_id);
                $prevH = $h;
                $prevA = $a;
                if ($winSide === 'home') {
                    $h = (int) $ev->team_point_number;
                } else {
                    $a = (int) $ev->team_point_number;
                }

                // серия
                if ($winSide === $runSide) {
                    $runLen++;
                } else {
                    $runSide = $winSide;
                    $runLen = 1;
                    $runFrom = [$prevH, $prevA];
                }
                if ($winSide && ($bestRun[$winSide] === null || $runLen > $bestRun[$winSide]['len'])) {
                    $bestRun[$winSide] = ['len' => $runLen, 'from' => $runFrom, 'to' => [$h, $a]];
                }

                // ход сета
                $d = $h - $a;
                $diff[] = $d;
                if ($d > $maxLead['home']['lead']) {
                    $maxLead['home'] = ['lead' => $d, 'rally' => $rn, 'home' => $h, 'away' => $a];
                }
                if (-$d > $maxLead['away']['lead']) {
                    $maxLead['away'] = ['lead' => -$d, 'rally' => $rn, 'home' => $h, 'away' => $a];
                }

                $serverSide = $serve['servers'][$rn] ?? null;
                if ($serverSide !== null) {
                    $recvSide = $serverSide === 'home' ? 'away' : 'home';
                    $serveTotals[$serverSide]['serve_total']++;
                    $serveTotals[$recvSide]['recv_total']++;
                    if ($winSide === $serverSide) {
                        $serveTotals[$serverSide]['serve_won']++;
                    } else {
                        $serveTotals[$recvSide]['recv_won']++;
                    }
                }

                $isOpp = in_array($ev->action_type, self::ERROR_ACTIONS, true);
                $rallies[] = [
                    'kind'        => 'point',
                    'seq'         => (int) $ev->seq,
                    'rn'          => $rn,
                    'set'         => (int) $sn,
                    'side'        => $winSide,
                    'action'      => $ev->action_type,
                    'attack_type' => $ev->action_type === MatchRallyEvent::ACTION_KILL ? $ev->attack_type : null,
                    'is_error'    => $isOpp,
                    'player_id'   => $ev->player_id ? (int) $ev->player_id : null,
                    'player'      => $ev->player_id ? ($names[(int) $ev->player_id] ?? null) : null,
                    'player_side' => $ev->player_id ? $sideOf($ev->stat_team_id ? (int) $ev->stat_team_id : null) : null,
                    'score_home'  => $h,
                    'score_away'  => $a,
                    'server_side' => $serverSide,
                    'by'          => $this->recorderName($ev),
                ];
            }

            // замены/либеро — в ленту по seq
            foreach ($lineupBySet->get($sn, collect()) as $le) {
                $rallies[] = [
                    'kind'       => 'lineup',
                    'seq'        => (int) $le->seq,
                    'set'        => (int) $sn,
                    'side'       => $sideOf((int) $le->team_id),
                    'type'       => $le->type,
                    'out_id'     => $le->out_user_id ? (int) $le->out_user_id : null,
                    'in_id'      => $le->in_user_id ? (int) $le->in_user_id : null,
                    'out'        => $le->out_user_id ? ($names[(int) $le->out_user_id] ?? null) : null,
                    'in'         => $le->in_user_id ? ($names[(int) $le->in_user_id] ?? null) : null,
                    'score_home' => (int) $le->score_home,
                    'score_away' => (int) $le->score_away,
                    'by'         => $this->recorderName($le),
                ];
            }
            usort($rallies, fn ($x, $y) => $x['seq'] <=> $y['seq']);

            $best = null;
            foreach (['home', 'away'] as $side) {
                if ($bestRun[$side] && ($best === null || $bestRun[$side]['len'] > $best['len'])) {
                    $best = $bestRun[$side] + ['side' => $side];
                }
            }

            $sets[(int) $sn] = [
                'number'     => (int) $sn,
                'score'      => ['home' => $h, 'away' => $a],
                'rallies'    => $rallies,
                'diff'       => $diff,
                'max_lead'   => $maxLead,
                'best_run'   => $best,
                'runs'       => $bestRun,
                'serve'      => $serve,
            ];
        }

        // ---- очки по происхождению ----
        $origin = ['home' => $this->emptyOrigin(), 'away' => $this->emptyOrigin()];
        foreach ($events as $ev) {
            $side = $sideOf((int) $ev->team_id);
            if (!$side) {
                continue;
            }
            $o = &$origin[$side];
            $o['total']++;
            match ($ev->action_type) {
                MatchRallyEvent::ACTION_KILL                => [$o['attack']++, $o['at'][in_array($ev->attack_type, MatchRallyEvent::ATTACK_TYPES, true) ? $ev->attack_type : 'none']++],
                MatchRallyEvent::ACTION_BLOCK               => $o['block']++,
                MatchRallyEvent::ACTION_ACE                 => $o['ace']++,
                MatchRallyEvent::ACTION_OPP_SERVE_ERROR     => [$o['opp_total']++, $o['opp_serve']++],
                MatchRallyEvent::ACTION_OPP_ATTACK_ERROR    => [$o['opp_total']++, $o['opp_attack']++],
                MatchRallyEvent::ACTION_OPP_RECEPTION_ERROR => [$o['opp_total']++, $o['opp_reception']++],
                MatchRallyEvent::ACTION_OPP_BLOCK_ERROR     => [$o['opp_total']++, $o['opp_block']++],
                default                                     => $o['unattributed']++,
            };
            unset($o);
        }

        // ---- игроки: матч и по сетам ----
        $players = $this->playerTables($events, $roster, $names, $homeId, $awayId, $sideOf);

        // ---- составы и замены ----
        $lineup = $this->lineupBlock($match, $setLineups, $lineupEvents, $names, $sideOf, $homeId, $awayId);

        $first = $events->min('created_at');
        $last = $events->max('created_at');
        $stage = $match->stage;
        $event = $stage?->event;
        $court = $match->courtModel?->name ?? $match->court;

        $serveSummary = [];
        foreach (['home', 'away'] as $side) {
            $t = $serveTotals[$side];
            $serveSummary[$side] = $t + [
                'serve_pct' => $t['serve_total'] > 0 ? round($t['serve_won'] / $t['serve_total'] * 100) : null,
                'recv_pct'  => $t['recv_total'] > 0 ? round($t['recv_won'] / $t['recv_total'] * 100) : null,
            ];
        }

        return [
            'match_id'  => (int) $match->id,
            'status'    => $match->status,
            'last_seq'  => (int) $match->last_seq,
            'teams'     => [
                'home' => ['id' => $homeId, 'name' => (string) ($match->teamHome?->name ?? '?')],
                'away' => ['id' => $awayId, 'name' => (string) ($match->teamAway?->name ?? '?')],
            ],
            'header'    => [
                'event_title' => (string) ($event?->title ?? ''),
                'stage'       => (string) ($stage?->name ?? ''),
                'venue'       => trim(implode(' · ', array_filter([$event?->location?->name, $court]))),
                'minutes'     => ($first && $last && $first->diffInSeconds($last) >= 600) ? (int) round($first->diffInSeconds($last) / 60) : null,   // < 10 мин — не показываем
                'scheduled'   => $match->scheduled_at,
            ],
            'set_numbers' => array_keys($sets),
            'sets'      => $sets,
            'origin'    => $origin,
            'serve'     => [
                'teams'        => $serveSummary,
                'sets_total'   => count($sets),
                'sets_used'    => $setsReliable,
            ],
            'players'   => $players,
            'has_block_errors'     => $this->anyAction($events, MatchRallyEvent::ACTION_OPP_BLOCK_ERROR),
            'has_reception_errors' => $this->anyAction($events, MatchRallyEvent::ACTION_OPP_RECEPTION_ERROR),
            'lineup'    => $lineup,
            'names'     => $names,
        ];
    }

    private function anyAction(Collection $events, string $action): bool
    {
        return $events->contains(fn ($e) => $e->action_type === $action);
    }

    private function emptyOrigin(): array
    {
        return ['total' => 0, 'attack' => 0, 'block' => 0, 'ace' => 0, 'opp_total' => 0,
            'opp_serve' => 0, 'opp_attack' => 0, 'opp_reception' => 0, 'opp_block' => 0, 'unattributed' => 0,
            // подтип атаки (атака = power + cut + poke + roll + tip + none)
            'at' => ['power' => 0, 'cut' => 0, 'poke' => 0, 'roll' => 0, 'tip' => 0, 'none' => 0]];
    }

    /**
     * Подающая команда в каждом розыгрыше сета (по номеру розыгрыша) и надёжность сета.
     *
     * @param Collection<int, MatchRallyEvent> $setEvents
     * @return array{servers: array<int,string>, reliable: bool, reason: string, first_side: ?string,
     *               first_source: ?string, anchors: int, supports: int, contradictions: int, bad_rallies: int[]}
     */
    public function analyseServe(Collection $setEvents, int $homeId, int $awayId, ?int $startTeam, ?int $markTeam): array
    {
        $sideOf = fn (?int $t) => $t === $homeId ? 'home' : ($t === $awayId ? 'away' : null);
        $other = fn (string $s) => $s === 'home' ? 'away' : 'home';

        $declared = $startTeam ?? $markTeam;
        $source = $startTeam ? 'start' : ($markTeam ? 'mark' : null);
        $conflict = $startTeam && $markTeam && $startTeam !== $markTeam;
        $firstSide = $declared ? $sideOf($declared) : null;

        $supports = $firstSide ? 1 : 0;
        $contradictions = 0;
        $anchors = 0;
        $bad = [];
        $servers = [];

        $setEvents = $setEvents->values();
        $prevWinner = null;
        foreach ($setEvents as $i => $ev) {
            $rn = $i + 1;
            $win = $sideOf((int) $ev->team_id);
            $server = $rn === 1 ? $firstSide : $prevWinner;

            $impliedServer = match ($ev->action_type) {
                MatchRallyEvent::ACTION_ACE              => $win,
                MatchRallyEvent::ACTION_OPP_SERVE_ERROR  => $win ? $other($win) : null,
                default                                  => null,
            };

            if ($impliedServer !== null) {
                $anchors++;
                if ($server === null) {
                    // розыгрыш №1 без отметки — первого подающего выдаёт сам якорь
                    $firstSide = $impliedServer;
                    $server = $impliedServer;
                    $source = 'anchor';
                    $supports++;
                } elseif ($server === $impliedServer) {
                    $supports++;
                } else {
                    $contradictions++;
                    $bad[] = $rn;
                }
            }

            if ($server !== null) {
                $servers[$rn] = $server;
            }
            $prevWinner = $win;
        }

        $reason = 'ok';
        if ($conflict) {
            $reason = 'conflict';
        } elseif ($contradictions > 0) {
            $reason = 'contradiction';
        } elseif ($supports === 0) {
            $reason = 'no_anchor';
        }
        $reliable = $reason === 'ok';

        return [
            'servers'        => $reliable ? $servers : [],
            'reliable'       => $reliable,
            'reason'         => $reason,
            'first_side'     => $firstSide,
            'first_source'   => $source,
            'anchors'        => $anchors,
            'supports'       => $supports,
            'contradictions' => $contradictions,
            'bad_rallies'    => $bad,
        ];
    }

    /**
     * @return array{home: array, away: array} — строки игроков: scopes['all'|номер сета] => счётчики
     */
    private function playerTables(Collection $events, array $roster, array $names, int $homeId, int $awayId, callable $sideOf): array
    {
        $blank = fn () => ['attack' => 0, 'block' => 0, 'ace' => 0, 'serve_err' => 0, 'attack_err' => 0,
            'reception_err' => 0, 'block_err' => 0, 'plus' => 0, 'minus' => 0, 'balance' => 0,
            'at' => ['power' => 0, 'cut' => 0, 'poke' => 0, 'roll' => 0, 'tip' => 0, 'none' => 0]];

        $rows = ['home' => [], 'away' => []];
        $ensure = function (string $side, int $uid) use (&$rows, $names, $blank) {
            $rows[$side][$uid] ??= ['user_id' => $uid, 'name' => $names[$uid] ?? ('#' . $uid), 'scopes' => ['all' => $blank()]];
        };

        foreach ([$homeId => 'home', $awayId => 'away'] as $teamId => $side) {
            foreach (array_keys($roster[$teamId] ?? []) as $uid) {
                $ensure($side, (int) $uid);
            }
        }

        $fieldOf = [
            MatchRallyEvent::ACTION_KILL                => 'attack',
            MatchRallyEvent::ACTION_BLOCK               => 'block',
            MatchRallyEvent::ACTION_ACE                 => 'ace',
            MatchRallyEvent::ACTION_OPP_SERVE_ERROR     => 'serve_err',
            MatchRallyEvent::ACTION_OPP_ATTACK_ERROR    => 'attack_err',
            MatchRallyEvent::ACTION_OPP_RECEPTION_ERROR => 'reception_err',
            MatchRallyEvent::ACTION_OPP_BLOCK_ERROR     => 'block_err',
        ];

        foreach ($events as $ev) {
            $field = $fieldOf[$ev->action_type] ?? null;
            if (!$field || !$ev->player_id || !$ev->stat_team_id) {
                continue;
            }
            $side = $sideOf((int) $ev->stat_team_id);
            if (!$side) {
                continue;
            }
            $uid = (int) $ev->player_id;
            $ensure($side, $uid);
            $set = (int) $ev->set_number;
            $rows[$side][$uid]['scopes'][$set] ??= $blank();
            $atKey = in_array($ev->attack_type, MatchRallyEvent::ATTACK_TYPES, true) ? $ev->attack_type : 'none';
            foreach (['all', $set] as $scope) {
                $rows[$side][$uid]['scopes'][$scope][$field]++;
                if ($field === 'attack') {
                    $rows[$side][$uid]['scopes'][$scope]['at'][$atKey]++;
                }
            }
        }

        foreach (['home', 'away'] as $side) {
            foreach ($rows[$side] as $uid => &$row) {
                foreach ($row['scopes'] as &$c) {
                    $c['plus'] = $c['attack'] + $c['block'] + $c['ace'];
                    $c['minus'] = $c['serve_err'] + $c['attack_err'] + $c['reception_err'] + $c['block_err'];
                    $c['balance'] = $c['plus'] - $c['minus'];
                }
                unset($c);
            }
            unset($row);
            uasort($rows[$side], fn ($x, $y) => $y['scopes']['all']['balance'] <=> $x['scopes']['all']['balance']
                ?: $y['scopes']['all']['plus'] <=> $x['scopes']['all']['plus']);
            $rows[$side] = array_values($rows[$side]);
        }

        return $rows;
    }

    /**
     * Блок «Составы и замены»: null, если состав в матче не велся.
     */
    private function lineupBlock(TournamentMatch $match, Collection $setLineups, Collection $lineupEvents, array $names, callable $sideOf, int $homeId, int $awayId): ?array
    {
        if ($setLineups->isEmpty() && $lineupEvents->isEmpty()) {
            return null;
        }

        $tracked = $setLineups->pluck('set_number')->merge($lineupEvents->pluck('set_number'))->unique()->sort()->values();
        $sets = [];
        $played = ['home' => [], 'away' => []];

        foreach ($tracked as $sn) {
            $teams = ['home' => ['court' => [], 'libero' => []], 'away' => ['court' => [], 'libero' => []]];
            $inSet = ['home' => [], 'away' => []];

            foreach ($setLineups->where('set_number', $sn)->sortBy(fn ($r) => $r->zone ?? 99) as $row) {
                $side = $sideOf((int) $row->team_id);
                if (!$side) {
                    continue;
                }
                $item = ['user_id' => (int) $row->user_id, 'name' => $names[(int) $row->user_id] ?? '#' . $row->user_id,
                    'position' => $row->position, 'zone' => $row->zone === null ? null : (int) $row->zone];
                $teams[$side][$row->position === 'libero' ? 'libero' : 'court'][] = $item;
                $inSet[$side][(int) $row->user_id] = true;
            }

            $evs = [];
            foreach ($lineupEvents->where('set_number', $sn)->sortBy('seq') as $le) {
                $side = $sideOf((int) $le->team_id);
                if (!$side) {
                    continue;
                }
                if ($le->in_user_id) {
                    $inSet[$side][(int) $le->in_user_id] = true;
                }
                $evs[] = [
                    'side'       => $side,
                    'type'       => $le->type,
                    'in'         => $le->in_user_id ? ($names[(int) $le->in_user_id] ?? null) : null,
                    'out'        => $le->out_user_id ? ($names[(int) $le->out_user_id] ?? null) : null,
                    'score_home' => (int) $le->score_home,
                    'score_away' => (int) $le->score_away,
                    'seq'        => (int) $le->seq,
                ];
            }

            foreach (['home', 'away'] as $side) {
                foreach (array_keys($inSet[$side]) as $uid) {
                    $played[$side][$uid] = ($played[$side][$uid] ?? 0) + 1;
                }
            }

            $sets[(int) $sn] = ['number' => (int) $sn, 'teams' => $teams, 'events' => $evs];
        }

        $sets_played = [];
        foreach (['home', 'away'] as $side) {
            arsort($played[$side]);
            $sets_played[$side] = collect($played[$side])->map(fn ($n, $uid) => [
                'user_id' => (int) $uid, 'name' => $names[(int) $uid] ?? '#' . $uid, 'sets' => (int) $n,
            ])->values()->all();
        }

        return ['sets' => $sets, 'sets_played' => $sets_played];
    }

    /** @return array<int,string> user_id => «Фамилия Имя» */
    private function resolveNames(Collection $ids): array
    {
        if ($ids->isEmpty()) {
            return [];
        }

        $out = [];
        foreach (DB::table('users')->whereIn('id', $ids->all())->get(['id', 'first_name', 'last_name', 'name']) as $u) {
            $out[(int) $u->id] = trim(($u->last_name ?? '') . ' ' . ($u->first_name ?? '')) ?: (string) ($u->name ?? ('#' . $u->id));
        }

        return $out;
    }

    private array $recorders = [];

    private function recorderName($ev): ?string
    {
        if (!empty($ev->recorded_by_name)) {
            return (string) $ev->recorded_by_name;
        }
        $uid = (int) ($ev->recorded_by_user_id ?? 0);
        if (!$uid) {
            return null;
        }
        if (!array_key_exists($uid, $this->recorders)) {
            $u = DB::table('users')->where('id', $uid)->first(['first_name', 'last_name']);
            $this->recorders[$uid] = $u ? (trim(($u->last_name ?? '') . ' ' . ($u->first_name ?? '')) ?: null) : null;
        }

        return $this->recorders[$uid];
    }
}
