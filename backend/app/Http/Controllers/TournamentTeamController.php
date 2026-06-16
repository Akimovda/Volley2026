<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventTeam;
use App\Models\EventTeamMember;
use App\Models\EventOccurrence;
use App\Services\TournamentTeamService;
use App\Services\TournamentTeamDistributionService;
use App\Models\EventTournamentSetting;
use App\Services\WaitlistService;
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

        $leagueForSubs        = null;
        $reserveForSubs       = collect();
        $tourStarted          = false;
        $existingSubstitutions = collect();

        if ($team->occurrence_id) {
            $seasonEvent = \App\Models\TournamentSeasonEvent::where('occurrence_id', $team->occurrence_id)->first();
            if ($seasonEvent?->league_id) {
                $leagueForSubs = \App\Models\TournamentLeague::find($seasonEvent->league_id);
                if ($leagueForSubs) {
                    $reserveForSubs = $leagueForSubs->leagueTeams()
                        ->where('status', 'reserve')
                        ->whereNotNull('user_id')
                        ->with('user:id,first_name,last_name')
                        ->get();
                }
            }
            if ($team->occurrence) {
                $tourStarted = now('UTC')->gte($team->occurrence->starts_at);
            }
            $existingSubstitutions = \App\Models\TeamSubstitution::where('team_id', $team->id)
                ->where('occurrence_id', $team->occurrence_id)
                ->whereIn('status', ['pending', 'confirmed'])
                ->with([
                    'originalPlayer:id,first_name,last_name',
                    'substitutePlayer:id,first_name,last_name',
                ])
                ->get()
                ->keyBy('original_player_id');
        }

        return view('tournaments.teams.show', [
            'event'                 => $event,
            'team'                  => $team,
            'teamRoleOptions'       => $service->getTeamRoleOptions(),
            'positionOptions'       => $service->getAvailablePositionOptions($team),
            'leagueForSubs'         => $leagueForSubs,
            'reserveForSubs'        => $reserveForSubs,
            'tourStarted'           => $tourStarted,
            'existingSubstitutions' => $existingSubstitutions,
        ]);
    }

    public function update(Request $request, Event $event, EventTeam $team): RedirectResponse
    {
        abort_unless((int) $team->event_id === (int) $event->id, 404);

        $user = $request->user();
        $isCaptain    = (int) $team->captain_user_id === (int) $user->id;
        $isOrganizer  = (int) $event->organizer_id  === (int) $user->id || $user->isAdmin();
        abort_unless($isCaptain || $isOrganizer, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255',
                Rule::unique('event_teams')->where(function ($q) use ($event, $team) {
                    $q->where('event_id', $event->id);
                    $team->occurrence_id ? $q->where('occurrence_id', $team->occurrence_id) : $q->whereNull('occurrence_id');
                })->ignore($team->id),
            ],
        ]);

        $team->update(['name' => trim($data['name'])]);
        $this->dispatchAnnounceRefresh($event, $team->occurrence_id ? (int) $team->occurrence_id : null);

        return redirect()
            ->route('tournamentTeams.show', [$event, $team])
            ->with('success', 'Название команды обновлено');
    }

    public function store(Request $request, Event $event, TournamentTeamService $service): RedirectResponse
    {
        $occurrenceId = (int) $request->input('occurrence_id', 0);

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255',
                \Illuminate\Validation\Rule::unique('event_teams')->where(function ($q) use ($event, $occurrenceId) {
                    $q->where('event_id', $event->id);
                    $occurrenceId > 0 ? $q->where('occurrence_id', $occurrenceId) : $q->whereNull('occurrence_id');
                }),
            ],
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

            $isOrganizerOrAdmin = (int) $event->organizer_id === (int) $request->user()->id
                || $request->user()->isAdmin();

            $team = $service->createTeam(
                event: $event,
                captain: $captainUser,
                name: $teamName,
                occurrenceId: !empty($data['occurrence_id']) ? (int) $data['occurrence_id'] : null,
                teamKind: $data['team_kind'] ?? null,
                captainPositionCode: $data['captain_position_code'] ?? null,
                autoApprove: $isOrganizerOrAdmin,
            );

            $this->dispatchAnnounceRefresh($event, !empty($data['occurrence_id']) ? (int) $data['occurrence_id'] : null);

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
            $this->dispatchAnnounceRefresh($event, $team->occurrence_id ? (int) $team->occurrence_id : null);

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
            $this->dispatchAnnounceRefresh($event, $team->occurrence_id ? (int) $team->occurrence_id : null);

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
        $canRemove = (int) $team->captain_user_id === (int) $request->user()->id
            || (int) $event->organizer_id === (int) $request->user()->id
            || $request->user()->isAdmin();
        abort_unless($canRemove, 403);

        try {
            $service->removeMember($team, $member->id, $request->user());
            $this->dispatchAnnounceRefresh($event, $team->occurrence_id ? (int) $team->occurrence_id : null);

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
            $this->dispatchAnnounceRefresh($event, $team->occurrence_id ? (int) $team->occurrence_id : null);
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

        $occurrenceId = $team->occurrence_id ? (int) $team->occurrence_id : null;

        // Удаляем заявку
        \App\Models\EventTeamApplication::where('event_team_id', $team->id)->delete();

        // Удаляем приглашения
        \App\Models\EventTeamInvite::where('event_team_id', $team->id)->delete();

        // Удаляем членов
        $team->members()->delete();

        // Удаляем команду
        $team->delete();

        $this->dispatchAnnounceRefresh($event, $occurrenceId);

        return redirect()
            ->route('tournament.setup', $event)
            ->with('success', "Команда удалена.");
    }

    /**
     * Организатор переводит команду в резерв.
     * beach_pair: расформировать + создать соло-пары для каждого участника (→ «Ищут партнёра»).
     * classic_team (лиговый): TournamentLeagueTeam.status='reserve' — появляется в блоке «Лист ожидания» на setup.
     * classic_team (нелиговый): EventTeam.reserve_position — появляется в счётчике резерва.
     */
    public function sendTeamToWaitlist(
        Request $request,
        Event $event,
        EventTeam $team,
        TournamentTeamService $service
    ): RedirectResponse {
        abort_unless((int) $team->event_id === (int) $event->id, 404);
        $user = $request->user();
        $isOrganizer = (int) $event->organizer_id === (int) $user->id || $user->isAdmin();
        abort_unless($isOrganizer, 403);

        $occurrenceId = $team->occurrence_id ? (int) $team->occurrence_id : null;
        $teamKind     = $team->team_kind;
        $teamName     = $team->name;

        $redirectUrl = route('tournament.setup', $event);
        if ($occurrenceId) {
            $redirectUrl .= '?occurrence_id=' . $occurrenceId;
        }

        if ($teamKind === 'beach_pair') {
            // Beach: расформировать + соло-пары → «Ищут партнёра»
            $members = $team->members()->with('user')
                ->where('confirmation_status', 'confirmed')
                ->get();

            \App\Models\EventTeamApplication::where('event_team_id', $team->id)->delete();
            \App\Models\EventTeamInvite::where('event_team_id', $team->id)->delete();
            $team->members()->delete();
            $team->delete();

            $errors   = [];
            $settings = EventTournamentSetting::where('event_id', $event->id)->first();
            $autoApprove = ($settings?->application_mode ?? 'manual') === 'auto';
            foreach ($members as $m) {
                $mu       = $m->user;
                $soloName = trim(($mu->last_name ?? '') . ' ' . ($mu->first_name ? mb_substr($mu->first_name, 0, 1) . '.' : ''));
                if ($soloName === '' || $soloName === '.') {
                    $soloName = $mu->name ?? 'Новая пара';
                }
                try {
                    $service->createTeam(
                        event: $event,
                        captain: $mu,
                        name: $soloName,
                        occurrenceId: $occurrenceId,
                        teamKind: 'beach_pair',
                        autoApprove: $autoApprove,
                    );
                } catch (\Exception $e) {
                    $errors[] = $e->getMessage();
                }
            }

            $this->dispatchAnnounceRefresh($event, $occurrenceId);

            $msg = "Команда «{$teamName}» переведена в резерв.";
            if ($errors) {
                $msg .= ' Ошибки: ' . implode('; ', $errors);
            }
            return redirect($redirectUrl)->with('success', $msg);
        }

        // Classic: перевести в резерв через правильный механизм (не occurrence_waitlist)
        $seasonEvent = $occurrenceId
            ? \App\Models\TournamentSeasonEvent::where('occurrence_id', $occurrenceId)->first()
            : null;

        if ($seasonEvent?->league_id) {
            // Лиговый турнир: TournamentLeagueTeam → reserve
            $league = \App\Models\TournamentLeague::find($seasonEvent->league_id);
            $leagueTeam = \App\Models\TournamentLeagueTeam::where('league_id', $seasonEvent->league_id)
                ->where('team_id', $team->id)
                ->first();

            if (!$leagueTeam) {
                $captainId = $team->captain_user_id
                    ?? $team->members()->where('role_code', 'captain')->value('user_id')
                    ?? $team->members()->first()?->user_id;
                if ($captainId) {
                    $leagueTeam = \App\Models\TournamentLeagueTeam::where('league_id', $seasonEvent->league_id)
                        ->where('user_id', $captainId)
                        ->first();
                }
            }

            if ($leagueTeam && $league) {
                $leagueTeam->eliminate($league->nextReservePosition());
            }
        } else {
            // Нелиговый турнир: EventTeam → reserve_position
            $maxReserve = \App\Models\EventTeam::where('event_id', $event->id)
                ->whereNotNull('reserve_position')
                ->max('reserve_position') ?? 0;
            $team->update([
                'reserve_position' => $maxReserve + 1,
                'status'           => 'reserve',
            ]);
        }

        $this->dispatchAnnounceRefresh($event, $occurrenceId);

        return redirect($redirectUrl)->with('success', "Команда «{$teamName}» переведена в резерв.");
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

        $this->dispatchAnnounceRefresh($event, !empty($data['occurrence_id']) ? (int) $data['occurrence_id'] : null);

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
        TournamentTeamService $service,
        WaitlistService $waitlistService
    ): RedirectResponse {
        abort_unless((int) $team->event_id === (int) $event->id, 404);

        $user = $request->user();
        abort_unless($user, 403);

        $addToWaitlist = $request->boolean('add_to_waitlist');
        $occurrenceId  = $team->occurrence_id ? (int) $team->occurrence_id : null;
        $teamKind      = $team->team_kind;

        // Запоминаем позицию участника до удаления (для classic_team waitlist)
        $memberPositionCode = null;
        if ($addToWaitlist && $occurrenceId && $teamKind !== 'beach_pair') {
            $member = $team->members()->where('user_id', $user->id)->first();
            $memberPositionCode = $member?->position_code ?: null;
        }

        try {
            $service->leaveTeam($team, (int) $user->id);
            $this->dispatchAnnounceRefresh($event, $occurrenceId);

            $successMsg = 'Вы покинули команду.';

            if ($addToWaitlist && $occurrenceId) {
                if ($teamKind === 'beach_pair') {
                    // Для пляжного турнира: создаём новую пару с игроком как капитаном
                    try {
                        $settings   = EventTournamentSetting::where('event_id', $event->id)->first();
                        $autoApprove = ($settings?->application_mode ?? 'manual') === 'auto';
                        $teamName   = trim(($user->last_name ?? '') . ' ' . ($user->first_name ? mb_substr($user->first_name, 0, 1) . '.' : ''));
                        if ($teamName === '' || $teamName === '.') {
                            $teamName = $user->name ?? 'Новая пара';
                        }
                        $newTeam = $service->createTeam(
                            event: $event,
                            captain: $user,
                            name: $teamName,
                            occurrenceId: $occurrenceId,
                            teamKind: 'beach_pair',
                            autoApprove: $autoApprove,
                        );
                        $successMsg .= ' Создана новая пара — ищите партнёра.';
                    } catch (\Exception $e) {
                        $successMsg .= ' Не удалось создать пару: ' . $e->getMessage();
                    }
                } else {
                    // Для обычного мероприятия: occurrence_waitlist
                    $occurrence = EventOccurrence::find($occurrenceId);
                    if ($occurrence) {
                        $positions = $memberPositionCode ? [$memberPositionCode] : [];
                        try {
                            $waitlistService->join($occurrence, $user, $positions);
                            $successMsg .= ' Вы добавлены в лист ожидания.';
                        } catch (\Exception $e) {
                            $successMsg .= ' Не удалось добавить в лист ожидания: ' . $e->getMessage();
                        }
                    }
                }
            }

            return redirect()
                ->route('events.show', $event)
                ->with('success', $successMsg);
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

    private function dispatchAnnounceRefresh(Event $event, ?int $occurrenceId): void
    {
        $hasChannels = DB::table('event_notification_channels')
            ->where('event_id', (int) $event->id)
            ->exists();
        if (!$hasChannels) {
            return;
        }

        if ($occurrenceId) {
            \App\Jobs\RefreshOccurrenceAnnouncementJob::dispatch($occurrenceId)
                ->onQueue('default')
                ->afterCommit();
            return;
        }

        $ids = DB::table('event_occurrences')
            ->where('event_id', (int) $event->id)
            ->where('starts_at', '>=', now())
            ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
            ->pluck('id');

        foreach ($ids as $id) {
            \App\Jobs\RefreshOccurrenceAnnouncementJob::dispatch((int) $id)
                ->onQueue('default')
                ->afterCommit();
        }
    }

    // ──────────────────────────────────────────────────────
    // Подтверждение/отклонение места из резерва
    // ──────────────────────────────────────────────────────

    public function reserveConfirmShow(Request $request, Event $event, EventTeam $team, string $token)
    {
        abort_unless((int) $team->event_id === (int) $event->id, 404);
        abort_unless($team->confirmation_token === $token, 404);
        abort_unless($team->isInReserve(), 404);

        $expired = $team->confirmation_expires_at?->isPast() ?? false;

        return view('tournaments.teams.reserve_confirm', compact('event', 'team', 'token', 'expired'));
    }

    public function reserveConfirm(Request $request, Event $event, EventTeam $team, TournamentTeamService $service): RedirectResponse
    {
        abort_unless((int) $team->event_id === (int) $event->id, 404);
        abort_unless($team->isInReserve(), 404);
        $isCaptain = (int) $team->captain_user_id === (int) $request->user()?->id;
        $isOrganizer = (int) $event->organizer_id === (int) $request->user()?->id || $request->user()?->isAdmin();
        abort_unless($isCaptain || $isOrganizer, 403);

        $token = $request->input('token', $team->confirmation_token ?? '');

        try {
            $service->confirmEventReserveSpot($team, $token);
            $this->dispatchAnnounceRefresh($event, $team->occurrence_id ? (int) $team->occurrence_id : null);
            return redirect()->route('tournamentTeams.show', [$event, $team])
                ->with('success', 'Участие подтверждено! Ваша команда в основном составе.');
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reserveDecline(Request $request, Event $event, EventTeam $team, TournamentTeamService $service): RedirectResponse
    {
        abort_unless((int) $team->event_id === (int) $event->id, 404);
        abort_unless($team->isInReserve(), 404);
        $isCaptain = (int) $team->captain_user_id === (int) $request->user()?->id;
        $isOrganizer = (int) $event->organizer_id === (int) $request->user()?->id || $request->user()?->isAdmin();
        abort_unless($isCaptain || $isOrganizer, 403);

        $service->expireEventReserveOffer($team);
        return redirect()->route('events.show', $event)
            ->with('success', 'Место передано следующей команде в очереди.');
    }

    /**
     * Капитан расформировывает команду.
     */
    public function transferCaptain(
        Request $request,
        Event $event,
        EventTeam $team,
        TournamentTeamService $service
    ): RedirectResponse {
        abort_unless((int) $team->event_id === (int) $event->id, 404);
        abort_unless((int) $team->captain_user_id === (int) $request->user()->id, 403);

        $data = $request->validate([
            'new_captain_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        try {
            $service->transferCaptain($team, (int) $data['new_captain_user_id'], (int) $request->user()->id);
            return back()->with('success', 'Капитанство передано.');
        } catch (DomainException $e) {
            return back()->withErrors(['transfer' => $e->getMessage()]);
        }
    }

    public function disbandTeam(
        Request $request,
        Event $event,
        EventTeam $team,
        TournamentTeamService $service
    ): RedirectResponse {
        abort_unless((int) $team->event_id === (int) $event->id, 404);

        $user = $request->user();
        abort_unless($user, 403);

        $isOrganizer = (int) $event->organizer_id === (int) $user->id || $user->isAdmin();
        $isCaptain   = (int) $team->captain_user_id === (int) $user->id;
        abort_unless($isCaptain || $isOrganizer, 403);

        try {
            $occurrenceId = $team->occurrence_id ? (int) $team->occurrence_id : null;
            $service->disbandTeam($team, (int) $user->id, force: $isOrganizer && !$isCaptain);
            $this->dispatchAnnounceRefresh($event, $occurrenceId);

            return redirect()
                ->route('events.show', $event)
                ->with('success', 'Команда расформирована.');
        } catch (DomainException $e) {
            return back()->withErrors(['disband' => $e->getMessage()]);
        }
    }

}