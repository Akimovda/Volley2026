<?php

namespace App\Services;

use App\Models\MatchLineupEvent;
use App\Models\MatchRallyEvent;
use App\Models\TournamentMatch;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Читает match_rally_events и собирает ленту "ход матча" — по розыгрышу на строку,
 * с бегущим счётом обеих команд. Только чтение, ничего не пишет.
 *
 * Счёт НЕ вычисляется по action_type — очко всегда у команды из event.team_id
 * (см. диагностику: маппинг "тип → чьё очко" не нужен для арифметики, только
 * для подписи действия). team_point_number — уже готовый счёт команды-события
 * на момент розыгрыша; счёт другой стороны переносится с её последнего события
 * в этом же сете (или 0, если она ещё не начинала считать).
 */
class MatchProgressService
{
    private const POSITION_CODES = ['setter', 'outside', 'opposite', 'middle', 'libero'];

    /**
     * @return array{has_progress: bool, sets: array<int, array{rallies: array, final_score: array{home:int,away:int}}>}
     */
    public function build(int $matchId, bool $withRecorder = false): array
    {
        $match = TournamentMatch::findOrFail($matchId);
        $events = MatchRallyEvent::where('match_id', $matchId)
            ->orderBy('set_number')->orderBy('seq')
            ->get();
        $lineup = MatchLineupEvent::where('match_id', $matchId)->orderBy('set_number')->orderBy('seq')->get();

        return $this->assemble($match, $events, $lineup, $withRecorder);
    }

    /**
     * Пачкой для набора матчей одной страницы — без N+1 (аналог
     * PlayerMatchStatsService::getMatchStatsTableForMatches()).
     *
     * @param Collection<int, TournamentMatch> $matches
     * @return array<int, array{has_progress: bool, sets: array}> keyed by match_id
     */
    public function buildForMatches(Collection $matches): array
    {
        $matchIds = $matches->pluck('id');
        if ($matchIds->isEmpty()) {
            return [];
        }

        $allEvents = MatchRallyEvent::whereIn('match_id', $matchIds)
            ->orderBy('set_number')->orderBy('seq')
            ->get()
            ->groupBy('match_id');
        $allLineup = MatchLineupEvent::whereIn('match_id', $matchIds)
            ->orderBy('set_number')->orderBy('seq')
            ->get()
            ->groupBy('match_id');

        $result = [];
        foreach ($matches as $match) {
            $result[$match->id] = $this->assemble(
                $match, $allEvents->get($match->id, collect()), $allLineup->get($match->id, collect())
            );
        }

        return $result;
    }

    /**
     * Очки и события состава вперемешку, по seq внутри партии. Элементы:
     * kind=point (как раньше, плюс 'kind') | substitution | libero_in | libero_out.
     * Счёт на момент события состава — снимок score_home/away самого события.
     *
     * @param Collection<int, MatchRallyEvent> $events уже отсортированы set_number, seq
     * @param Collection<int, MatchLineupEvent> $lineup уже отсортированы set_number, seq
     */
    private function assemble(TournamentMatch $match, Collection $events, ?Collection $lineup = null, bool $withRecorder = false): array
    {
        $lineup ??= collect();
        if ($events->isEmpty() && $lineup->isEmpty()) {
            return ['has_progress' => false, 'sets' => []];
        }

        $playerIds = $events->pluck('player_id')
            ->merge($lineup->pluck('out_user_id'))->merge($lineup->pluck('in_user_id'))
            ->filter()->unique();
        $players = $this->resolvePlayers($match, $playerIds);
        $tz = $match->stage?->event?->timezone ?: 'Europe/Moscow';

        $setNumbers = $events->pluck('set_number')->merge($lineup->pluck('set_number'))->unique()->sort()->values();
        $eventsBySet = $events->groupBy('set_number');
        $lineupBySet = $lineup->groupBy('set_number');

        $sets = [];
        foreach ($setNumbers as $setNumber) {
            $homeScore = 0;
            $awayScore = 0;
            $rallies = [];
            $rallyN = 0;

            $merged = collect($eventsBySet->get($setNumber, collect())->all())
                ->merge($lineupBySet->get($setNumber, collect())->all())
                ->sortBy('seq')->values();

            foreach ($merged as $event) {
                $isHome = (int) $event->team_id === (int) $match->team_home_id;

                if ($event instanceof MatchLineupEvent) {
                    $rallies[] = $this->lineupItem($match, $event, $isHome, $players, $tz, $withRecorder);
                    continue;
                }

                $rallyN++;
                if ($isHome) {
                    $homeScore = $event->team_point_number;
                } else {
                    $awayScore = $event->team_point_number;
                }

                $rallies[] = [
                    'kind'        => 'point',
                    'seq'         => (int) $event->seq,
                    'rally_n'     => $rallyN,
                    'id'          => $event->id,
                    'action_type' => $event->action_type,
                    'team_side'   => $isHome ? 'home' : 'away',
                    'is_own_action' => in_array($event->action_type, MatchRallyEvent::SELF_ACTIONS, true),
                    'player'      => $event->player_id ? ($players[$event->player_id] ?? null) : null,
                    'score_home'  => $homeScore,
                    'score_away'  => $awayScore,
                ];
            }

            $sets[(int) $setNumber] = [
                'rallies'     => $rallies,
                'final_score' => ['home' => $homeScore, 'away' => $awayScore],
            ];
        }

        return ['has_progress' => true, 'sets' => $sets];
    }

    /** Строка ленты для события состава + готовый (уже переведённый) текст попапа. */
    private function lineupItem(TournamentMatch $match, MatchLineupEvent $ev, bool $isHome, array $players, string $tz, bool $withRecorder): array
    {
        $teamName = (string) (($isHome ? $match->teamHome : $match->teamAway)?->name ?? '');
        $out = $players[$ev->out_user_id] ?? null;
        $in  = $players[$ev->in_user_id] ?? null;
        $fullName = fn (?array $p) => $p ? $p['name'] : '—';
        $surname = fn (?array $p) => $p ? (trim(explode(' ', $p['name'])[0] ?? '') ?: $p['name']) : '—';
        $posName = function (?string $code) {
            return $code ? __('tournaments.pos_abbr_' . $code) : null;
        };
        $posFull = fn (?string $code) => $code ? __('tournaments.pos_full_' . $code) : null;
        $zone = $ev->zone;

        $where = collect([$posFull($ev->position), $zone ? __('tournaments.lineup_zone_n', ['n' => $zone]) : null])->filter()->implode(', ');

        $lines = [];
        if ($ev->type === MatchLineupEvent::TYPE_SUBSTITUTION) {
            $label = __('tournaments.lineup_ev_substitution');
            $lines[] = __('tournaments.lineup_popup_out', ['name' => $fullName($out)]);
            $lines[] = __('tournaments.lineup_popup_in', ['name' => $fullName($in)]) . ($where ? " ($where)" : '');
            $summary = $surname($out) . ' → ' . $surname($in);
        } elseif ($ev->type === MatchLineupEvent::TYPE_LIBERO_IN) {
            $label = __('tournaments.lineup_ev_libero');
            $lines[] = __('tournaments.lineup_popup_libero_in', [
                'libero' => $fullName($in), 'name' => $fullName($out),
                'pos' => $posFull($ev->position) ?: '—',
            ]) . ($zone ? ' · ' . __('tournaments.lineup_zone_n', ['n' => $zone]) : '');
            $summary = $surname($in) . ' → ' . $surname($out);
        } else {
            $label = __('tournaments.lineup_ev_libero');
            $lines[] = __('tournaments.lineup_popup_libero_out', ['name' => $fullName($in), 'libero' => $fullName($out)])
                . ($where ? " ($where)" : '');
            $summary = $surname($out) . ' ↔ ' . $surname($in);
        }

        if ($withRecorder && ($ev->recorded_by_user_id || $ev->recorded_by_name)) {
            $by = $ev->recorded_by_name;
            if (!$by && $ev->recorded_by_user_id) {
                $u = User::find($ev->recorded_by_user_id);
                $by = $u ? trim(($u->last_name ?? '') . ' ' . ($u->first_name ?? '')) : null;
            }
            if ($by) {
                $lines[] = __('tournaments.lineup_popup_by', [
                    'name' => $by, 'time' => $ev->created_at?->copy()->setTimezone($tz)->format('H:i') ?? '',
                ]);
            }
        }

        return [
            'kind'       => $ev->type,
            'seq'        => (int) $ev->seq,
            'id'         => $ev->id,
            'team_side'  => $isHome ? 'home' : 'away',
            'team_name'  => $teamName,
            'label'      => $label,
            'summary'    => $summary,
            'score_home' => (int) $ev->score_home,
            'score_away' => (int) $ev->score_away,
            'popup'      => [
                'title' => $label . ' · ' . $teamName,
                'sub'   => __('tournaments.lineup_popup_set_score', [
                    'set' => $ev->set_number, 'h' => $ev->score_home, 'a' => $ev->score_away,
                ]),
                'lines' => $lines,
            ],
        ];
    }

    /**
     * @param Collection<int, int> $playerIds уникальные user_id, встреченные в событиях
     * @return array<int, array{id:int,name:string,avatar_url:string,gender:?string,position_code:?string,team_id:?int,team_kind:?string,role_code:?string}>
     */
    private function resolvePlayers(TournamentMatch $match, Collection $playerIds): array
    {
        if ($playerIds->isEmpty()) {
            return [];
        }

        // join на event_teams — для opp_*_error действий player_id указывает на игрока
        // ПРОТИВОПОЛОЖНОЙ команды относительно team_side розыгрыша, поэтому team_id/team_kind
        // нужно резолвить по самому игроку, а не выводить из team_home_id/team_away_id матча.
        $members = DB::table('event_team_members')
            ->join('event_teams', 'event_teams.id', '=', 'event_team_members.event_team_id')
            ->whereIn('event_team_members.event_team_id', [$match->team_home_id, $match->team_away_id])
            ->whereIn('event_team_members.user_id', $playerIds)
            ->select(
                'event_team_members.user_id',
                'event_team_members.position_code',
                'event_team_members.role_code',
                'event_team_members.event_team_id',
                'event_teams.team_kind'
            )
            ->get()
            ->keyBy('user_id');

        // with('media') — profile_photo_url резолвит аватар через getMedia('photos') внутри
        // accessor'а; без eager load это N+1 запрос на каждого игрока ленты.
        $users = User::whereIn('id', $playerIds)->with('media')->get()->keyBy('id');

        $result = [];
        foreach ($playerIds as $id) {
            $user = $users->get($id);
            if (!$user) {
                continue;
            }

            $member = $members->get($id);
            $positionCode = $member->position_code ?? null;
            if (!$positionCode && $member && in_array($member->role_code, self::POSITION_CODES, true)) {
                $positionCode = $member->role_code;
            }

            $result[$id] = [
                'id'            => $id,
                'name'          => trim(($user->last_name ?? '') . ' ' . ($user->first_name ?? '')),
                'avatar_url'    => $user->profile_photo_url,
                'gender'        => $user->gender,
                'position_code' => $positionCode,
                'team_id'       => $member->event_team_id ?? null,
                'team_kind'     => $member->team_kind ?? null,
                'role_code'     => $member->role_code ?? null,
            ];
        }

        return $result;
    }
}
