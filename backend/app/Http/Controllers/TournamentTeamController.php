<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventTeam;
use App\Models\EventTeamMember;
use App\Services\TournamentTeamService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TournamentTeamController extends Controller
{
    public function show(Event $event, EventTeam $team, TournamentTeamService $service): View
    {
        abort_unless((int) $team->event_id === (int) $event->id, 404);

        $team->load([
            'event.tournamentSetting',
            'occurrence',
            'captain',
            'members.user',
            'application',
            'invites.invitedUser',
            'invites.invitedByUser',
        ]);

        return view('tournaments.teams.show', [
            'event' => $event,
            'team' => $team,
            'teamRoleOptions' => $service->getTeamRoleOptions(),
            'positionOptions' => $service->getAvailablePositionOptions($team),
        ]);
    }

    public function store(Request $request, Event $event, TournamentTeamService $service): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', \Illuminate\Validation\Rule::unique('event_teams')->where('event_id', $event->id)],
            'occurrence_id' => ['nullable', 'integer', 'exists:event_occurrences,id'],
            'team_kind' => ['nullable', 'string', 'in:classic_team,beach_pair'],
            'captain_position_code' => ['nullable', 'string', 'in:setter,outside,opposite,middle,libero'],
        ]);

        try {
            $team = $service->createTeam(
                event: $event,
                captain: $request->user(),
                name: $data['name'],
                occurrenceId: !empty($data['occurrence_id']) ? (int) $data['occurrence_id'] : null,
                teamKind: $data['team_kind'] ?? null,
                captainPositionCode: $data['captain_position_code'] ?? null,
            );

            return redirect()
                ->route('tournamentTeams.show', [$event, $team])
                ->with('success', 'Команда создана ✅');
        } catch (DomainException $e) {
            return back()
                ->withErrors(['team' => $e->getMessage()])
                ->withInput();
        }
    }

    public function confirmMember(
        Request $request,
        Event $event,
        EventTeam $team,
        EventTeamMember $member,
        TournamentTeamService $service
    ): RedirectResponse {
        abort_unless((int) $team->event_id === (int) $event->id, 404);
        abort_unless((int) $member->event_team_id === (int) $team->id, 404);
        abort_unless((int) $team->captain_user_id === (int) $request->user()->id, 403);

        try {
            $service->confirmMember($team, $member->id, $request->user());

            return back()->with('success', 'Игрок подтверждён ✅');
        } catch (DomainException $e) {
            return back()->withErrors([
                'member' => $e->getMessage(),
            ]);
        }
    }

    public function declineMember(
        Request $request,
        Event $event,
        EventTeam $team,
        EventTeamMember $member,
        TournamentTeamService $service
    ): RedirectResponse {
        abort_unless((int) $team->event_id === (int) $event->id, 404);
        abort_unless((int) $member->event_team_id === (int) $team->id, 404);
        abort_unless((int) $team->captain_user_id === (int) $request->user()->id, 403);

        try {
            $service->declineMember($team, $member->id, $request->user());

            return back()->with('success', 'Игрок отклонён.');
        } catch (DomainException $e) {
            return back()->withErrors([
                'member' => $e->getMessage(),
            ]);
        }
    }

    public function removeMember(
        Request $request,
        Event $event,
        EventTeam $team,
        EventTeamMember $member,
        TournamentTeamService $service
    ): RedirectResponse {
        abort_unless((int) $team->event_id === (int) $event->id, 404);
        abort_unless((int) $member->event_team_id === (int) $team->id, 404);
        abort_unless((int) $team->captain_user_id === (int) $request->user()->id, 403);

        try {
            $service->removeMember($team, $member->id, $request->user());

            return back()->with('success', 'Игрок удалён из команды.');
        } catch (DomainException $e) {
            return back()->withErrors([
                'member' => $e->getMessage(),
            ]);
        }
    }

    public function submitApplication(
        Request $request,
        Event $event,
        EventTeam $team,
        TournamentTeamService $service
    ): RedirectResponse {
        abort_unless((int) $team->event_id === (int) $event->id, 404);
        abort_unless((int) $team->captain_user_id === (int) $request->user()->id, 403);

        try {
            $service->submitApplication($team, $request->user());

            return back()->with('success', 'Заявка на турнир подана ✅');
        } catch (DomainException $e) {
            return back()->withErrors([
                'application' => $e->getMessage(),
            ]);
        }
    }
}