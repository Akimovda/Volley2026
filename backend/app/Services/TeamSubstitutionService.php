<?php

namespace App\Services;

use App\Models\EventOccurrence;
use App\Models\EventTeam;
use App\Models\EventTeamMember;
use App\Models\TeamSubstitution;
use App\Models\TournamentLeague;
use App\Models\TournamentLeagueTeam;
use App\Models\User;
use Illuminate\Support\Collection;

class TeamSubstitutionService
{
    /**
     * Капитан приглашает замену вместо одного из участников.
     */
    public function inviteSubstitute(
        int    $leagueId,
        int    $occurrenceId,
        int    $teamId,
        int    $originalPlayerId,
        int    $substitutePlayerId,
        string $source
    ): TeamSubstitution {
        $this->guardOccurrenceNotStarted($occurrenceId);
        $this->guardOriginalPlayerInTeam($teamId, $originalPlayerId);

        return TeamSubstitution::updateOrCreate(
            ['occurrence_id' => $occurrenceId, 'team_id' => $teamId],
            [
                'league_id'               => $leagueId,
                'original_player_id'      => $originalPlayerId,
                'substitute_player_id'    => $substitutePlayerId,
                'substitute_source'       => $source,
                'initiated_by'            => 'captain',
                'captain_confirmed_at'    => now(),
                'substitute_confirmed_at' => null,
                'status'                  => 'pending',
            ]
        );
    }

    /**
     * Игрок из резерва предлагает себя как замену.
     */
    public function requestJoinAsSubstitute(
        int    $leagueId,
        int    $occurrenceId,
        int    $teamId,
        int    $originalPlayerId,
        int    $substitutePlayerId,
        string $source
    ): TeamSubstitution {
        $this->guardOccurrenceNotStarted($occurrenceId);

        return TeamSubstitution::updateOrCreate(
            ['occurrence_id' => $occurrenceId, 'team_id' => $teamId],
            [
                'league_id'               => $leagueId,
                'original_player_id'      => $originalPlayerId,
                'substitute_player_id'    => $substitutePlayerId,
                'substitute_source'       => $source,
                'initiated_by'            => 'substitute',
                'captain_confirmed_at'    => null,
                'substitute_confirmed_at' => now(),
                'status'                  => 'pending',
            ]
        );
    }

    /**
     * Подтверждение второй стороной. Когда оба подтвердили → status=confirmed.
     */
    public function confirm(TeamSubstitution $sub, int $userId): TeamSubstitution
    {
        $team = $sub->team;

        if ($userId === $team?->captain_user_id) {
            $sub->captain_confirmed_at = now();
        } elseif ($userId === $sub->substitute_player_id) {
            $sub->substitute_confirmed_at = now();
        } else {
            abort(403, 'Not authorized to confirm this substitution');
        }

        if ($sub->isFullyConfirmed()) {
            $sub->status = 'confirmed';
        }

        $sub->save();
        return $sub;
    }

    /**
     * Отмена замены.
     */
    public function cancel(TeamSubstitution $sub): void
    {
        $this->guardOccurrenceNotStarted($sub->occurrence_id);
        $sub->update(['status' => 'cancelled']);
    }

    /**
     * Возвращает фактический состав команды с учётом confirmed замены.
     * Каждый элемент: ['user' => User, 'is_substitute' => bool, 'original_user' => ?User]
     */
    public function getActualRoster(int $teamId, int $occurrenceId): Collection
    {
        $team = EventTeam::with(['members' => fn($q) => $q->where('confirmation_status', 'confirmed'), 'members.user'])
            ->find($teamId);

        if (!$team) return collect();

        $sub = TeamSubstitution::where('team_id', $teamId)
            ->where('occurrence_id', $occurrenceId)
            ->where('status', 'confirmed')
            ->with(['substitutePlayer'])
            ->first();

        return $team->members->map(function ($m) use ($sub) {
            if ($sub && $m->user_id === $sub->original_player_id) {
                return [
                    'user'          => $sub->substitutePlayer,
                    'is_substitute' => true,
                    'original_user' => $m->user,
                    'member'        => $m,
                ];
            }
            return [
                'user'          => $m->user,
                'is_substitute' => false,
                'original_user' => null,
                'member'        => $m,
            ];
        });
    }

    /**
     * Загрузить все confirmed замены для списка команд/тура одним запросом.
     * Возвращает коллекцию keyed by team_id.
     */
    public function loadSubstitutionsForOccurrence(Collection $teamIds, int $occurrenceId): Collection
    {
        return TeamSubstitution::whereIn('team_id', $teamIds)
            ->where('occurrence_id', $occurrenceId)
            ->whereIn('status', ['pending', 'confirmed'])
            ->with(['originalPlayer:id,first_name,last_name', 'substitutePlayer:id,first_name,last_name'])
            ->get()
            ->keyBy('team_id');
    }

    // -------------------------------------------------------------------------

    private function guardOccurrenceNotStarted(int $occurrenceId): void
    {
        $occ = EventOccurrence::find($occurrenceId);
        if ($occ && now('UTC')->gte($occ->starts_at)) {
            abort(422, __('tournaments.substitution_tour_started'));
        }
    }

    private function guardOriginalPlayerInTeam(int $teamId, int $userId): void
    {
        $inTeam = EventTeamMember::where('event_team_id', $teamId)
            ->where('user_id', $userId)
            ->where('confirmation_status', 'confirmed')
            ->exists();

        if (!$inTeam) {
            abort(422, __('tournaments.substitution_player_not_in_team'));
        }
    }
}
