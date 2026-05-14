<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventTeam;
use App\Models\EventTeamMember;
use App\Models\EventOccurrence;
use App\Services\TournamentTeamService;
use App\Services\TournamentTeamDistributionService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'name' => ['nullable', 'string', 'max:255', \Illuminate\Validation\Rule::unique('event_teams')->where('event_id', $event->id)],
            'occurrence_id' => ['nullable', 'integer', 'exists:event_occurrences,id'],
            'team_kind' => ['nullable', 'string', 'in:classic_team,beach_pair'],
            'captain_position_code' => ['nullable', 'string', 'in:setter,outside,opposite,middle,libero'],
            'captain_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        try {
            $captainUser = !empty($data['captain_user_id'])
                ? \App\Models\User::findOrFail($data['captain_user_id'])
                : $request->user();

            // Проверка незаполненных полей профиля капитана
            $occurrence = !empty($data['occurrence_id'])
                ? \App\Models\EventOccurrence::find((int) $data['occurrence_id'])
                : null;
            $missingFields = $captainUser->getMissingFieldsForEvent($event, $occurrence);
            if (!empty($missingFields)) {
                $returnTo = route('events.show', array_filter([
                    'event' => (int) $event->id,
                    'occurrence' => $occurrence ? (int) $occurrence->id : null,
                ]));
                $profileUrl = route('profile.complete') . '?' . http_build_query([
                    'missing'   => implode(',', $missingFields),
                    'return_to' => $returnTo,
                ]);
                return redirect($profileUrl);
            }

            // Авто-название по фамилии капитана если пустое
            $teamName = trim($data['name'] ?? '');
            if (empty($teamName)) {
                $capName = $captainUser->last_name ?: ($captainUser->first_name ?: $captainUser->name);
                $teamName = 'Команда ' . $capName;
            }

            $team = $service->createTeam(
                event: $event,
                captain: $captainUser,
                name: $teamName,
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

    /**
     * Организатор/админ добавляет игрока в команду напрямую (без invite).
     */
    public function addMemberByOrganizer(
        Request $request,
        Event $event,
        EventTeam $team,
        TournamentTeamService $service
    ): RedirectResponse {
        abort_unless((int) $team->event_id === (int) $event->id, 404);

        $user    = $request->user();
        $isAdmin = ($user->role ?? null) === 'admin';
        $isOrg   = (int) $event->organizer_id === (int) $user->id;
        abort_unless($isAdmin || $isOrg, 403);

        $positionRules = (string) $team->team_kind === 'classic_team'
            ? ['nullable', 'string', 'in:setter,outside,opposite,middle,libero']
            : ['nullable'];

        $data = $request->validate([
            'user_id'       => ['required', 'integer', 'exists:users,id'],
            'team_role'     => ['required', 'string', 'in:player,reserve'],
            'position_code' => $positionRules,
        ]);

        $player = \App\Models\User::findOrFail((int) $data['user_id']);

        try {
            $service->addMemberByOrganizer(
                team:         $team,
                player:       $player,
                organizer:    $user,
                teamRole:     (string) $data['team_role'],
                positionCode: $data['position_code'] ?? null,
            );
            return back()->with('success', 'Игрок добавлен в команду ✅');
        } catch (DomainException $e) {
            return back()->withErrors(['add_member' => $e->getMessage()]);
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

        $allowIncomplete = (bool) $request->input('allow_incomplete', false);

        try {
            $service->submitApplication($team, $request->user(), $allowIncomplete);

            return back()->with('success', 'Заявка на турнир подана ✅');
        } catch (DomainException $e) {
            return back()->withErrors([
                'application' => $e->getMessage(),
            ]);
        }
    }

    public function revokeApplication(
        Request $request,
        Event $event,
        EventTeam $team,
        TournamentTeamService $service
    ): RedirectResponse {
        abort_unless((int) $team->event_id === (int) $event->id, 404);

        $user = $request->user();
        $isCaptain = (int) $team->captain_user_id === (int) $user->id;
        $isAdmin = ($user->role ?? null) === 'admin';
        abort_unless($isCaptain || $isAdmin, 403);

        try {
            $service->revokeApplication($team, $user);
            return back()->with('success', __('events.tapp_revoked_msg'));
        } catch (DomainException $e) {
            return back()->withErrors(['application' => $e->getMessage()]);
        }
    }

    public function destroy(
        Request $request,
        Event $event,
        EventTeam $team
    ): RedirectResponse {
        abort_unless((int) $team->event_id === (int) $event->id, 404);

        $user = $request->user();
        $isOrganizer = (int) $event->organizer_id === (int) $user->id || $user->isAdmin();
        abort_unless($isOrganizer, 403, 'Только организатор может удалить команду.');

        // Удаляем заявку
        \App\Models\EventTeamApplication::where('event_team_id', $team->id)->delete();

        // Удаляем приглашения
        \App\Models\EventTeamInvite::where('event_team_id', $team->id)->delete();

        // Удаляем членов
        $team->members()->delete();

        // Удаляем команду
        $team->delete();

        return redirect()
            ->route('tournament.setup', $event)
            ->with('success', "Команда удалена.");
    }


    /**
     * Игрок отправляет запрос на вступление в пару с вакантным местом.
     */
    public function joinRequest(
        Request $request,
        Event $event,
        EventTeam $team,
        TournamentTeamService $service
    ): RedirectResponse {
        abort_unless((int) $team->event_id === (int) $event->id, 404);
        $user = $request->user();
        abort_unless($user, 403);

        // Проверка незаполненных полей профиля
        $occurrence = $team->occurrence_id ? \App\Models\EventOccurrence::find($team->occurrence_id) : null;
        $missingFields = $user->getMissingFieldsForEvent($event, $occurrence);
        if (!empty($missingFields)) {
            $returnTo = route('events.show', array_filter([
                'event' => (int) $event->id,
                'occurrence' => $occurrence ? (int) $occurrence->id : null,
            ]));
            $profileUrl = route('profile.complete') . '?' . http_build_query([
                'missing'   => implode(',', $missingFields),
                'return_to' => $returnTo,
            ]);
            return redirect($profileUrl);
        }

        try {
            $service->joinRequest($team, $user);
            return back()->with('success', 'Запрос на вступление отправлен. Ожидайте ответа капитана.');
        } catch (DomainException $e) {
            return back()->withErrors(['join' => $e->getMessage()]);
        }
    }

    /**
     * Капитан сохраняет текущую команду как шаблон в профиль.
     */
    public function saveToProfile(Request $request, Event $event, EventTeam $team): RedirectResponse
    {
        $user = $request->user();
        if (!$user) return redirect()->route('login');
        abort_unless((int) $team->captain_user_id === (int) $user->id, 403);

        $team->load('members.user');
        $name = $request->input('team_name', $team->name);

        $direction = (string) ($event->direction ?? 'classic');
        $subtype   = (string) ($event->gameSettings?->subtype ?? '');

        DB::beginTransaction();
        try {
            $userTeam = \App\Models\UserTeam::create([
                'user_id'   => $user->id,
                'name'      => $name,
                'direction' => $direction,
                'subtype'   => $subtype ?: null,
            ]);

            foreach ($team->members as $member) {
                \App\Models\UserTeamMember::create([
                    'user_team_id'  => $userTeam->id,
                    'user_id'       => (int) $member->user_id,
                    'role_code'     => $member->team_role ?: $member->role_code ?: 'player',
                    'position_code' => $member->position_code ?: null,
                ]);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Не удалось сохранить команду: ' . $e->getMessage());
        }

        return back()->with('success', 'Команда «' . e($name) . '» сохранена в профиле ✅');
    }

    /**
     * Создать EventTeam из сохранённого шаблона UserTeam.
     */
    public function fromSaved(Request $request, Event $event, TournamentTeamService $service): RedirectResponse
    {
        $user = $request->user();
        if (!$user) return redirect()->route('login');

        $data = $request->validate([
            'user_team_id'          => ['required', 'integer', 'exists:user_teams,id'],
            'occurrence_id'         => ['nullable', 'integer', 'exists:event_occurrences,id'],
            'captain_position_code' => ['nullable', 'string', 'in:setter,outside,opposite,middle,libero'],
        ]);

        $userTeam = \App\Models\UserTeam::with('members.user')->find((int) $data['user_team_id']);
        if (!$userTeam || (int) $userTeam->user_id !== (int) $user->id) abort(403);

        $event->loadMissing(['gameSettings', 'tournamentSetting']);

        // Проверка размера команды
        $validationSvc = app(\App\Services\UserTeamValidationService::class);
        $sizeError = $validationSvc->checkTeamSize($userTeam, $event);
        if ($sizeError) {
            return redirect()->route('user.teams.edit', $userTeam->id)
                ->with('team_size_error', $sizeError)
                ->withInput(['event_id' => $event->id])
                ->with('return_event_id', $event->id);
        }

        // Валидация участников
        $occurrence = null;
        if (!empty($data['occurrence_id'])) {
            $occurrence = \App\Models\EventOccurrence::find((int) $data['occurrence_id']);
        }
        $memberErrors = $validationSvc->validateForEvent($userTeam, $event, $occurrence);

        if ($memberErrors) {
            return redirect()
                ->route('user.teams.edit', ['team' => $userTeam->id, 'event_id' => $event->id])
                ->with('team_validation_errors', $memberErrors)
                ->with('return_event_id', $event->id);
        }

        // Позиция капитана
        $captainPos = $data['captain_position_code']
            ?? $userTeam->members->firstWhere('user_id', $user->id)?->position_code;

        try {
            $team = $service->createTeam(
                event: $event,
                captain: $user,
                name: $userTeam->name,
                occurrenceId: !empty($data['occurrence_id']) ? (int) $data['occurrence_id'] : null,
                teamKind: $event->direction === 'beach' ? 'beach_pair' : 'classic_team',
                captainPositionCode: $captainPos,
            );

            // Приглашаем остальных участников
            foreach ($userTeam->members as $member) {
                if ((int) $member->user_id === (int) $user->id) continue;
                if (!$member->user) continue;
                try {
                    app(\App\Services\TournamentTeamInviteService::class)->createInvite(
                        team: $team,
                        invitedUserId: (int) $member->user_id,
                        invitedByUserId: (int) $user->id,
                        teamRole: $member->role_code ?: 'player',
                        positionCode: $member->position_code ?: null,
                    );
                } catch (\Exception $e) {
                    // Пропускаем ошибки приглашения (игрок уже в команде и т.д.)
                }
            }
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('tournamentTeams.show', [$event, $team])
            ->with('success', 'Команда создана из шаблона ✅ Участники получили приглашения.');
    }

    /**
     * Игрок покидает команду.
     */
    public function leaveTeam(
        Request $request,
        Event $event,
        EventTeam $team,
        TournamentTeamService $service
    ): RedirectResponse {
        abort_unless((int) $team->event_id === (int) $event->id, 404);

        $user = $request->user();
        abort_unless($user, 403);

        try {
            $service->leaveTeam($team, (int) $user->id);

            return redirect()
                ->route('events.show', $event)
                ->with('success', 'Вы покинули команду.');
        } catch (DomainException $e) {
            return back()->withErrors(['leave' => $e->getMessage()]);
        }
    }

    /**
     * Случайное распределение индивидуально записавшихся по командам.
     * Доступно только организатору / администратору.
     */
    public function distributeIndividual(
        Request $request,
        Event $event,
        TournamentTeamDistributionService $service
    ): \Illuminate\Http\JsonResponse {
        $user = $request->user();
        abort_unless($user, 403);
        abort_unless(
            $user->role === 'admin' || (int)$event->organizer_id === (int)$user->id,
            403
        );

        $occurrenceId = (int)$request->input('occurrence_id', 0);
        $occurrence   = $occurrenceId
            ? EventOccurrence::where('event_id', $event->id)->findOrFail($occurrenceId)
            : EventOccurrence::where('event_id', $event->id)->orderBy('starts_at')->firstOrFail();

        $result = $service->distributeRandom($event, $occurrence);

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    /**
     * Капитан расформировывает команду.
     */
    public function disbandTeam(
        Request $request,
        Event $event,
        EventTeam $team,
        TournamentTeamService $service
    ): RedirectResponse {
        abort_unless((int) $team->event_id === (int) $event->id, 404);

        $user = $request->user();
        abort_unless($user, 403);

        try {
            $service->disbandTeam($team, (int) $user->id);

            return redirect()
                ->route('events.show', $event)
                ->with('success', 'Команда расформирована.');
        } catch (DomainException $e) {
            return back()->withErrors(['disband' => $e->getMessage()]);
        }
    }

}