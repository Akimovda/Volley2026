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
    public function __construct(private MatchLineupService $lineup) {}

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

        $teams = [];
        foreach ($sideTeams as $side => $tid) {
            $t = $d['teams'][$tid];
            $court = [];
            foreach ($t['court'] as $uid => $c) {
                $court[] = [
                    'id' => (int) $uid, 'name' => $nm($tid, $uid), 'position' => $c['position'],
                    'zone' => $c['zone'], 'libero' => (bool) $c['libero'], 'for_user_id' => $c['for_user_id'],
                ];
            }
            usort($court, fn ($a, $b) => ($a['zone'] ?? 99) <=> ($b['zone'] ?? 99));

            $held = [];
            foreach ($t['held'] as $uid => $h) {
                $held[] = ['id' => (int) $uid, 'name' => $nm($tid, $uid), 'position' => $h['position'], 'zone' => $h['zone']];
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
                    $list[] = ['id' => (int) $uid, 'name' => $p['name'], 'position' => $p['position'], 'team_role' => $p['team_role']];
                }
                $setup['teams'][$side]['roster'] = $list;
            }
        }

        return [
            'enabled'    => true,
            'profile'    => $profile,
            'has_start'  => $hasStart,
            'needs_start' => !$hasStart && !$decided,
            'can_edit_start' => $hasStart && !$hasEvents && !$decided,
            'teams'      => $teams,
            'setup'      => $setup,
        ];
    }
}
