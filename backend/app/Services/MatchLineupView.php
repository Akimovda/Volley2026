<?php

namespace App\Services;

use App\Models\MatchLineupEvent;
use App\Models\MatchRallyEvent;
use App\Models\TournamentMatch;

/**
 * Данные для экрана поочкового ввода: площадка/скамейка обеих команд, черновик расстановки,
 * флаги «нужна расстановка» / «можно изменить расстановку». Только чтение.
 */
class MatchLineupView
{
    public function __construct(private MatchLineupService $lineup, private PlayerCardService $cards) {}

    /**
     * @return array{enabled:bool, ...}
     */
    public function forRally(TournamentMatch $match, int $setNumber, bool $decided): array
    {
        $profile = $this->lineup->profile($match);
        if (!$profile['enabled']) {
            return ['enabled' => false];
        }

        $roster = $this->lineup->roster($match);
        $d = $this->lineup->describe($match, $setNumber);
        $hasStart = $d['has_start'];
        $hasEvents = MatchRallyEvent::where('match_id', $match->id)->where('set_number', $setNumber)->exists()
            || MatchLineupEvent::where('match_id', $match->id)->where('set_number', $setNumber)->exists();

        $sideTeams = ['home' => (int) $match->team_home_id, 'away' => (int) $match->team_away_id];
        $nm = fn (int $tid, int $uid) => $roster[$tid][$uid]['name'] ?? ('#' . $uid);

        // Карточки игроков (фото/инициалы/номер) — одним набором на оба состава
        $eventId = (int) ($match->relationLoaded('stage') ? $match->stage : $match->stage()->first())?->event_id;
        $allIds = [];
        foreach ($roster as $players) {
            $allIds = array_merge($allIds, array_keys($players));
        }
        $cards = $this->cards->cards($allIds, $eventId);
        $rs = $this->lineup->rotationState($match, $setNumber);
        $size = (int) $profile['on_court_size'];
        $styles = app(\App\Services\Broadcast\TeamStyleService::class);
        $teamModels = \App\Models\EventTeam::whereIn('id', array_values($sideTeams))->get()->keyBy('id');
        $colors = [];
        foreach ($sideTeams as $side => $tid) {
            $colors[$side] = $teamModels->get($tid) ? $styles->colorFor($teamModels->get($tid)) : '#2967BA';
        }

        $teams = [];
        foreach ($sideTeams as $side => $tid) {
            $t = $d['teams'][$tid];
            $court = [];
            $rot = (int) ($rs['rot'][$tid] ?? 0);
            foreach ($t['court'] as $uid => $c) {
                $slot = $c['zone'] === null ? null : (int) $c['zone'];
                $court[] = [
                    'id' => (int) $uid, 'name' => $nm($tid, $uid), 'position' => $c['position'],
                    // зона — ФИЗИЧЕСКАЯ сейчас (с учётом переходов); slot — «место» из стартового состава/замен
                    'zone' => $slot === null ? null : MatchLineupService::physicalZone($slot, $rot, $size),
                    'slot' => $slot,
                    'libero' => (bool) $c['libero'], 'for_user_id' => $c['for_user_id'],
                ];
            }
            usort($court, fn ($a, $b) => ($a['zone'] ?? 99) <=> ($b['zone'] ?? 99));

            $held = [];
            foreach ($t['held'] as $uid => $h) {
                $hs = $h['zone'] === null ? null : (int) $h['zone'];
                $held[] = [
                    'id' => (int) $uid, 'name' => $nm($tid, $uid), 'position' => $h['position'],
                    'zone' => $hs === null ? null : MatchLineupService::physicalZone($hs, $rot, $size), 'slot' => $hs,
                ];
            }

            $bench = [];
            foreach ($t['bench'] as $b) {
                $bench[] = [
                    'id' => (int) $b['user_id'], 'name' => $b['name'], 'position' => $b['position'],
                    'is_libero' => (bool) $b['is_libero'],
                ];
            }

            $liberoOnCourt = $t['libero_id'] && isset($t['court'][$t['libero_id']]);
            $teams[$side] = [
                'team_id'   => $tid,
                'court'     => $court,
                'held'      => $held,
                'bench'     => $bench,
                'libero_id' => $t['libero_id'],
                'libero_on_court' => (bool) $liberoOnCourt,
                'color'     => $colors[$side],
                'text'      => PlayerCardService::textOn($colors[$side]),
            ];
        }

        // Черновик для экрана расстановки: сохранённый старт (если есть) или предзаполнение
        $setup = null;
        if (!$decided) {
            $setup = ['teams' => [], 'first_serve_side' => null];
            if ($hasStart) {
                $first = $d['first_serve_team_id'];
                $setup['first_serve_side'] = $first === $sideTeams['home'] ? 'home' : ($first === $sideTeams['away'] ? 'away' : null);
                foreach ($sideTeams as $side => $tid) {
                    $court = [];
                    foreach ($d['teams'][$tid]['court'] as $uid => $c) {
                        if ($c['libero']) {
                            continue;
                        }
                        $court[] = ['user_id' => (int) $uid, 'position' => $c['position'], 'zone' => $c['zone']];
                    }
                    foreach ($d['teams'][$tid]['held'] as $uid => $h) { // либеро на площадке — основной на месте
                        $court[] = ['user_id' => (int) $uid, 'position' => $h['position'], 'zone' => $h['zone']];
                    }
                    $setup['teams'][$side] = ['court' => $court, 'libero' => $d['teams'][$tid]['libero_id']];
                }
            } else {
                $def = $this->lineup->defaultSetStart($match, $setNumber);
                $first = $def['first_serve_team_id'];
                $setup['first_serve_side'] = $first === $sideTeams['home'] ? 'home' : ($first === $sideTeams['away'] ? 'away' : null);
                foreach ($sideTeams as $side => $tid) {
                    $setup['teams'][$side] = [
                        'court'  => $def['teams'][$tid]['court'],
                        'libero' => $def['teams'][$tid]['libero'],
                    ];
                }
            }
            foreach ($sideTeams as $side => $tid) {
                $list = [];
                foreach ($roster[$tid] ?? [] as $uid => $p) {
                    $cd = $cards[(int) $uid] ?? null;
                    $list[] = [
                        'id' => (int) $uid, 'name' => $p['name'], 'position' => $p['position'], 'team_role' => $p['team_role'],
                        'surname' => $cd['surname'] ?? $p['name'], 'first' => $cd['first'] ?? '', 'initials' => $cd['initials'] ?? '?',
                        'photo' => $cd['photo'] ?? null, 'number' => $cd['number'] ?? null,
                    ];
                }
                $setup['teams'][$side]['roster'] = $list;
            }
        }

        // Мини-площадка пульта: физические зоны, цвет команды, подающий
        $mini = null;
        if ($hasStart) {
            $serving = $rs['serving_team_id'];
            $mini = ['size' => $size, 'serving_side' => null, 'server' => null, 'sig' => $setNumber . ':' . ($rs['rot'][$sideTeams['home']] ?? 0) . ':' . ($rs['rot'][$sideTeams['away']] ?? 0),
                'rot' => [], 'rotated_side' => null, 'teams' => []];
            foreach ($sideTeams as $side => $tid) {
                $color = $colors[$side];
                $cells = [];
                foreach ($teams[$side]['court'] as $c) {
                    $cd = $cards[$c['id']] ?? ['surname' => $c['name'], 'first' => '', 'initials' => '?', 'photo' => null, 'number' => null];
                    $pos = $c['libero'] ? 'libero' : $c['position'];
                    $cells[] = $cd + [
                        'zone' => $c['zone'], 'libero' => $c['libero'], 'for_user_id' => $c['for_user_id'], 'pos' => $pos,
                        'pos_abbr' => $pos ? __('tournaments.pos_abbr_' . $pos) : '',
                        'pos_full' => $pos ? __('tournaments.pos_full_' . $pos) : '',
                    ];
                }
                $isServing = $serving === $tid;
                if ($isServing) {
                    $mini['serving_side'] = $side;
                    foreach ($cells as $i => $c) {
                        if ((int) $c['zone'] !== 1) {
                            continue;
                        }
                        if (!empty($c['libero'])) {
                            // Либеро в зоне 1 у подающей команды: подавать он не может — кольцо не рисуем, просим вернуть заменённого игрока
                            $cells[$i]['serving_blocked'] = true;
                            $back = !empty($c['for_user_id']) ? ($cards[(int) $c['for_user_id']] ?? null) : null;
                            $mini['libero_serve'] = [
                                'number' => $back['number'] ?? null, 'surname' => $back['surname'] ?? '',
                                'team' => (string) ($teamModels->get($tid)?->name ?? ''),
                            ];
                        } else {
                            $mini['server'] = ['id' => $c['id'], 'surname' => $c['surname'], 'number' => $c['number'], 'team' => (string) ($teamModels->get($tid)?->name ?? '')];
                        }
                    }
                }
                $mini['rot'][$side] = (int) ($rs['rot'][$tid] ?? 0);
                if (($rs['last_rotated_team_id'] ?? null) === $tid) {
                    $mini['rotated_side'] = $side;
                }
                $mini['teams'][$side] = [
                    'team_id' => $tid, 'name' => (string) ($teamModels->get($tid)?->name ?? ''), 'color' => $color,
                    'text' => PlayerCardService::textOn($color), 'serving' => $isServing, 'cells' => $cells,
                ];
            }
        }

        // Подсказка по либеро «Вернуть X»: (а) либеро стоит в передней линии (замещённый игрок перешёл в зону 4);
        // (б) команда подаёт, а в зоне 1 стоит либеро. Только 6 на площадке (5-1 с либеро).
        if ($mini) {
            $mini['hints'] = [];
            if ($size === 6) {
                foreach ($sideTeams as $side => $tid) {
                    $tm = $mini['teams'][$side];
                    $lib = collect($tm['cells'])->first(fn ($c) => !empty($c['libero']));
                    if (!$lib) {
                        continue;
                    }
                    $z = (int) $lib['zone'];
                    $reason = in_array($z, [2, 3, 4], true) ? 'front' : (($tm['serving'] && $z === 1) ? 'serve' : null);
                    if (!$reason) {
                        continue;
                    }
                    $held = collect($teams[$side]['held'])->firstWhere('id', (int) $lib['for_user_id']);
                    $pc = $cards[(int) $lib['for_user_id']] ?? null;
                    if (!$pc) {
                        continue;
                    }
                    $pp = $held['position'] ?? null;
                    $mini['hints'][] = [
                        'side' => $side, 'team_id' => $tid, 'team' => $tm['name'], 'color' => $tm['color'], 'text' => $tm['text'],
                        'reason' => $reason, 'key' => $setNumber . ':' . $side . ':' . $reason . ':' . $mini['rot'][$side],
                        'libero' => ['id' => (int) $lib['id'], 'surname' => $lib['surname'], 'initials' => $lib['initials'], 'photo' => $lib['photo'], 'number' => $lib['number']],
                        'partner' => $pc + ['pos_abbr' => $pp ? __('tournaments.pos_abbr_' . $pp) : '', 'pos_full' => $pp ? __('tournaments.pos_full_' . $pp) : ''],
                    ];
                }
            }
        }

        return [
            'enabled'    => true,
            'mini'       => $mini,
            'profile'    => $profile,
            'has_start'  => $hasStart,
            'needs_start' => !$hasStart && !$decided,
            'can_edit_start' => $hasStart && !$hasEvents && !$decided,
            'teams'      => $teams,
            'setup'      => $setup,
        ];
    }
}
