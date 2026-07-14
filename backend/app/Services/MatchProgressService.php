<?php

namespace App\Services;

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
    public function build(int $matchId): array
    {
        $match = TournamentMatch::findOrFail($matchId);
        $events = MatchRallyEvent::where('match_id', $matchId)
            ->orderBy('set_number')->orderBy('id')
            ->get();

        return $this->assemble($match, $events);
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
            ->orderBy('set_number')->orderBy('id')
            ->get()
            ->groupBy('match_id');

        $result = [];
        foreach ($matches as $match) {
            $result[$match->id] = $this->assemble($match, $allEvents->get($match->id, collect()));
        }

        return $result;
    }

    /**
     * @param Collection<int, MatchRallyEvent> $events уже отсортированы set_number, id
     */
    private function assemble(TournamentMatch $match, Collection $events): array
    {
        if ($events->isEmpty()) {
            return ['has_progress' => false, 'sets' => []];
        }

        $players = $this->resolvePlayers($match, $events->pluck('player_id')->filter()->unique());

        $sets = [];
        foreach ($events->groupBy('set_number') as $setNumber => $setEvents) {
            $homeScore = 0;
            $awayScore = 0;
            $rallies = [];
            $rallyN = 0;

            foreach ($setEvents as $event) {
                $rallyN++;
                $isHome = (int) $event->team_id === (int) $match->team_home_id;

                if ($isHome) {
                    $homeScore = $event->team_point_number;
                } else {
                    $awayScore = $event->team_point_number;
                }

                $rallies[] = [
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

        $users = User::whereIn('id', $playerIds)->get()->keyBy('id');

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
