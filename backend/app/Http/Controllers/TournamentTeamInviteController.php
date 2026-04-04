<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventTeam;
use App\Services\TournamentTeamInviteService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TournamentTeamInviteController extends Controller
{
    public function store(
        Request $request,
        Event $event,
        EventTeam $team,
        TournamentTeamInviteService $service
    ): RedirectResponse {
        abort_unless((int) $team->event_id === (int) $event->id, 404);

        $user = $request->user();
        abort_unless($user, 403);
        abort_unless((int) $team->captain_user_id === (int) $user->id, 403);

        $positionRules = (string) $team->team_kind === 'classic_team'
            ? ['nullable', 'string', 'in:setter,outside,opposite,middle,libero']
            : ['nullable'];

        $data = $request->validate([
            'invited_user_id' => ['required', 'integer', 'exists:users,id'],
            'team_role' => ['required', 'string', 'in:player,reserve'],
            'position_code' => $positionRules,
        ]);

        try {
            $invite = $service->createInvite(
                team: $team,
                invitedUserId: (int) $data['invited_user_id'],
                invitedByUserId: (int) $user->id,
                teamRole: (string) $data['team_role'],
                positionCode: $data['position_code'] ?? null,
            );

            return back()->with(
                'success',
                'Ссылка-приглашение создана: ' . route('tournamentTeamInvites.show', ['token' => $invite->token])
            );
        } catch (DomainException $e) {
            return back()
                ->withErrors([
                    'invite_member' => $e->getMessage(),
                ])
                ->withInput();
        }
    }

    public function show(
        Request $request,
        string $token,
        TournamentTeamInviteService $service
    ): View|RedirectResponse {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        try {
            $invite = $service->getByTokenOrFail($token);

            // доступ только приглашённому пользователю
            if ((int) $invite->invited_user_id !== (int) $user->id) {
                abort(403);
            }

            $invite->load([
                'event.location.city',
                'event.tournamentSetting',
                'team.captain',
                'team.members.user',
            ]);

            return view('tournaments.invites.show', [
                'invite' => $invite,
                'team' => $invite->team,
                'event' => $invite->event,
            ]);
        } catch (DomainException $e) {
            return redirect()
                ->route('events.index')
                ->with('error', $e->getMessage());
        }
    }

    public function accept(
        Request $request,
        string $token,
        TournamentTeamInviteService $service
    ): RedirectResponse {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        try {
            $team = $service->acceptInvite($token, (int) $user->id);

            return redirect()
                ->route('tournamentTeams.show', [$team->event_id, $team->id])
                ->with('success', 'Вы вступили в команду.');
        } catch (DomainException $e) {
            return back()->withErrors([
                'invite' => $e->getMessage(),
            ]);
        }
    }

    public function decline(
        Request $request,
        string $token,
        TournamentTeamInviteService $service
    ): RedirectResponse {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        try {
            $service->declineInvite($token, (int) $user->id);

            return back()->with('success', 'Приглашение отклонено.');
        } catch (DomainException $e) {
            return back()->withErrors([
                'invite' => $e->getMessage(),
            ]);
        }
    }
}