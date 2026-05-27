<?php

namespace App\Http\Controllers;

use App\Models\EventTeam;
use App\Models\TeamSubstitution;
use App\Models\TournamentLeague;
use App\Services\TeamSubstitutionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TeamSubstitutionController extends Controller
{
    public function __construct(private TeamSubstitutionService $service) {}

    /**
     * Капитан создаёт запрос на замену одного из участников своей команды.
     */
    public function store(Request $request, TournamentLeague $league): RedirectResponse
    {
        $data = $request->validate([
            'occurrence_id'       => 'required|integer|exists:event_occurrences,id',
            'team_id'             => 'required|integer|exists:event_teams,id',
            'original_player_id'  => 'required|integer|exists:users,id',
            'substitute_player_id'=> 'required|integer|exists:users,id',
            'substitute_source'   => 'required|in:reserve,external',
        ]);

        $team = EventTeam::findOrFail($data['team_id']);

        if ((int) $team->captain_user_id !== (int) auth()->id()) {
            abort(403);
        }

        $this->service->inviteSubstitute(
            $league->id,
            (int) $data['occurrence_id'],
            (int) $data['team_id'],
            (int) $data['original_player_id'],
            (int) $data['substitute_player_id'],
            $data['substitute_source'],
        );

        return back()->with('success', __('tournaments.substitution_created'));
    }

    /**
     * Резервный игрок предлагает себя как замену капитану.
     */
    public function requestAsSubstitute(Request $request, TournamentLeague $league): RedirectResponse
    {
        $data = $request->validate([
            'occurrence_id'      => 'required|integer|exists:event_occurrences,id',
            'team_id'            => 'required|integer|exists:event_teams,id',
            'original_player_id' => 'required|integer|exists:users,id',
        ]);

        $this->service->requestJoinAsSubstitute(
            $league->id,
            (int) $data['occurrence_id'],
            (int) $data['team_id'],
            (int) $data['original_player_id'],
            (int) auth()->id(),
            'reserve',
        );

        return back()->with('success', __('tournaments.substitution_request_sent'));
    }

    /**
     * Подтверждение замены второй стороной.
     */
    public function confirm(Request $request, TeamSubstitution $substitution): RedirectResponse
    {
        $userId = (int) auth()->id();
        $captainId = (int) ($substitution->team?->captain_user_id ?? 0);

        if ($userId !== $captainId && $userId !== (int) $substitution->substitute_player_id) {
            abort(403);
        }

        $this->service->confirm($substitution, $userId);

        return back()->with('success', __('tournaments.substitution_confirmed'));
    }

    /**
     * Отмена замены.
     */
    public function cancel(Request $request, TeamSubstitution $substitution): RedirectResponse
    {
        $userId = (int) auth()->id();
        $captainId = (int) ($substitution->team?->captain_user_id ?? 0);

        if ($userId !== $captainId && $userId !== (int) $substitution->substitute_player_id) {
            abort(403);
        }

        $this->service->cancel($substitution);

        return back()->with('success', __('tournaments.substitution_cancelled'));
    }
}
