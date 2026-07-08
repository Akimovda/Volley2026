<?php

namespace App\Services;

use App\Jobs\ExpireEventTeamReserveJob;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\EventTeam;
use App\Models\EventTeamApplication;
use App\Models\EventTeamMember;
use App\Models\EventTeamMemberAudit;
use App\Models\EventTournamentSetting;
use App\Models\User;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Services\TournamentPaymentService;
use Illuminate\Support\Str;

final class TournamentTeamService
{
    public function createTeam(
        Event $event,
        User $captain,
        string $name,
        ?int $occurrenceId = null,
        ?string $teamKind = null,
        ?string $captainPositionCode = null,
        bool $autoApprove = false
    ): EventTeam {
        return DB::transaction(function () use ($event, $captain, $name, $occurrenceId, $teamKind, $captainPositionCode, $autoApprove) {
            $settings = $this->getSettings($event);

            $teamKind ??= $this->resolveTeamKindFromSettings($settings);
            
            if ((string) $teamKind === 'classic_team') {
                if (empty($captainPositionCode)) {
                    throw new DomainException('Для капитана классической команды нужно указать амплуа.');
                }

                $scheme = $settings?->getGameScheme() ?? '5x1';
                $allowedPositions = array_keys((array) config("volleyball.classic.{$scheme}.positions", []));

                if (!in_array($captainPositionCode, $allowedPositions, true)) {
                    throw new DomainException('Недопустимое амплуа капитана для этой схемы игры.');
                }
            } else {
                $captainPositionCode = null;
            }

            // Статус и позиция резерва при создании командой игроком (не autoApprove)
            $reservePos = null;
            $initialStatus = $autoApprove ? 'approved' : 'draft';

            if (!$autoApprove && $occurrenceId) {
                if ($event->season_id) {
                    // Лиговый турнир — статус submitted, лига добавляется ниже
                    $initialStatus = 'submitted';
                } elseif ($this->eventTournamentIsFull($event, $occurrenceId)) {
                    // Не-лиговый полный турнир → резерв
                    $reservePos = $this->nextEventReservePosition($event->id, $occurrenceId);
                    $initialStatus = 'submitted';
                }
            }

            $team = EventTeam::query()->create([
                'event_id' => $event->id,
                'occurrence_id' => $occurrenceId,
                'captain_user_id' => $captain->id,
                'name' => trim($name),
                'team_kind' => $teamKind,
                'status' => $initialStatus,
                'reserve_position' => $reservePos,
                'invite_code' => $this->generateInviteCode(),
                'is_complete' => false,
                'last_checked_at' => now(),
                'confirmed_at' => $autoApprove ? now() : null,
                'meta' => null,
            ]);

            $member = EventTeamMember::query()->create([
                'event_team_id' => $team->id,
                'user_id' => $captain->id,
                'role_code' => 'captain',
                'team_role' => 'captain',
                'position_code' => $captainPositionCode,
                'confirmation_status' => 'confirmed',
                'position_order' => 1,
                'invited_by_user_id' => $captain->id,
                'joined_at' => now(),
                'responded_at' => now(),
                'confirmed_at' => now(),
                'meta' => null,
            ]);

            $this->audit(
                team: $team,
                action: 'captain_added',
                userId: $captain->id,
                performedByUserId: $captain->id,
                newValue: [
                    'member_id' => $member->id,
                    'role_code' => 'captain',
                    'team_role' => 'captain',
                    'position_code' => $captainPositionCode,
                    'confirmation_status' => 'confirmed',
                ],
            );

            $fresh = $this->refreshTeamState($team->fresh());

            // Для лиговых турниров: добавляем команду в дивизион (active если есть место, reserve если нет)
            // Работает и для autoApprove (организатор через setup), и для self-регистрации
            if ($occurrenceId && $event->season_id) {
                try {
                    $seasonEvt = \App\Models\TournamentSeasonEvent::where('occurrence_id', $occurrenceId)->first();
                    if ($seasonEvt?->league_id) {
                        $league = \App\Models\TournamentLeague::find($seasonEvt->league_id);
                        if ($league) {
                            app(TournamentLeagueService::class)->addTeam(
                                league: $league,
                                team: $fresh,
                            );
                        }
                    }
                } catch (\InvalidArgumentException $e) {
                    if ($autoApprove) {
                        // Организатор: команда уже в лиге (sync создаёт через addTeam отдельно) — ок
                        report($e);
                    } else {
                        // Self-регистрация: нарушение требований → откат
                        $fresh->members()->delete();
                        $fresh->delete();
                        throw new \DomainException($e->getMessage());
                    }
                }
            }

            return $fresh;
        });
    }

    /**
     * Получить доступные роли в команде
     */
    public function getTeamRoleOptions(): array
    {
        return [
            'captain' => 'Капитан',
            'player' => 'Основной игрок',
            'reserve' => 'Запасной',
        ];
    }

    /**
     * Получить доступные позиции (амплуа) для команды — только те, где ещё есть
     * свободный слот (учитывает confirmed-участников, включая запасных с назначенным
     * амплуа — см. getPositionCapacity()). Используется при добавлении НОВОГО участника
     * (organizer add / invite-ссылка), где занятые слоты выбирать не нужно.
     */
    public function getAvailablePositionOptions(EventTeam $team): array
    {
        if ((string) $team->team_kind !== 'classic_team') {
            return [];
        }

        $result = [];
        foreach ($this->getPositionCapacity($team) as $code => $info) {
            if ($info['current'] >= $info['max']) {
                continue;
            }
            $free = $info['max'] - $info['current'];
            $result[$code] = "{$info['label']} (свободно {$free} из {$info['max']})";
        }

        return $result;
    }
    
    /**
     * Лейбл позиции на русском
     */
    private function positionLabel(string $code): string
    {
        return match ($code) {
            'setter' => 'Связующий',
            'outside' => 'Доигровщик',
            'opposite' => 'Диагональный',
            'middle' => 'Центральный блокирующий',
            'libero' => 'Либеро',
            default => $code,
        };
    }

    /**
     * Ёмкость по позициям для classic_team: сколько занято / сколько мест по каждой
     * позиции схемы. Считает confirmed-участников независимо от team_role (запасной,
     * которому назначили амплуа, занимает тот же слот, что и основной игрок) — это
     * согласуется с checkRequirements(), который тоже не разделяет по team_role.
     * Используется и для отображения, и для проверки при смене позиции участника.
     */
    public function getPositionCapacity(EventTeam $team): array
    {
        if ($team->team_kind !== 'classic_team') {
            return [];
        }

        $team->loadMissing(['event.tournamentSetting', 'members.user']);
        $settings = $this->getSettings($team->event);
        $scheme = $settings?->getGameScheme() ?? '5x1';

        $positions = (array) config("volleyball.classic.{$scheme}.positions", []);
        if ($settings?->require_libero && !array_key_exists('libero', $positions)) {
            $positions['libero'] = 1;
        }

        $confirmed = $team->members->where('confirmation_status', 'confirmed');

        $result = [];
        foreach ($positions as $code => $max) {
            $result[$code] = [
                'label'   => $this->positionLabel($code),
                'current' => $confirmed->where('position_code', $code)->count(),
                'max'     => (int) $max,
            ];
        }

        return $result;
    }

    /**
     * Разбивка укомплектованности по позициям для classic_team — для UI.
     */
    public function getPositionBreakdown(EventTeam $team): array
    {
        $rows = [];
        foreach ($this->getPositionCapacity($team) as $code => $info) {
            $rows[] = [
                'code'     => $code,
                'label'    => $info['label'],
                'current'  => $info['current'],
                'required' => $info['max'],
                'ok'       => $info['current'] >= $info['max'],
            ];
        }

        return $rows;
    }

    /**
     * Смена амплуа уже подтверждённого игрока (основного или запасного) капитаном/организатором.
     * Запрещает занять позицию, если по схеме турнира на неё уже набран лимит слотов
     * (запасной с назначенным амплуа занимает тот же слот, что и основной игрок).
     */
    public function changeMemberPosition(EventTeam $team, EventTeamMember $member, string $newPositionCode, int $performedByUserId): EventTeamMember
    {
        if ((string) $team->team_kind !== 'classic_team') {
            throw new DomainException('Смена позиции доступна только для классических команд.');
        }

        if ((int) $member->event_team_id !== (int) $team->id) {
            throw new DomainException('Игрок не состоит в этой команде.');
        }

        if (!in_array($member->team_role, ['player', 'captain', 'reserve'], true)) {
            throw new DomainException('Недопустимая роль участника.');
        }

        if ($member->confirmation_status !== 'confirmed') {
            throw new DomainException('Позицию можно менять только подтверждённым игрокам.');
        }

        $oldPositionCode = $member->position_code;
        if ($oldPositionCode === $newPositionCode) {
            return $member;
        }

        $capacity = $this->getPositionCapacity($team);
        if (!array_key_exists($newPositionCode, $capacity)) {
            throw new DomainException('Недопустимая позиция для этой схемы игры.');
        }

        $slot = $capacity[$newPositionCode];
        if ($slot['current'] >= $slot['max']) {
            throw new DomainException("Позиция «{$slot['label']}» уже занята ({$slot['current']} из {$slot['max']}).");
        }

        return DB::transaction(function () use ($team, $member, $newPositionCode, $oldPositionCode, $performedByUserId) {
            $member->position_code = $newPositionCode;
            if ($member->team_role !== 'captain') {
                $member->role_code = $newPositionCode;
            }
            $member->save();

            $this->audit(
                team: $team,
                action: 'position_changed',
                userId: $member->user_id,
                performedByUserId: $performedByUserId,
                oldValue: ['position_code' => $oldPositionCode],
                newValue: ['position_code' => $newPositionCode],
            );

            $this->refreshTeamState($team->fresh());

            return $member->fresh();
        });
    }

    /**
     * Перевод подтверждённого игрока между основным составом и запасными в рамках
     * одной команды (не путать с командным резервом/листом ожидания турнира —
     * здесь речь только про team_role внутри ростера одной команды).
     * Капитана переводить нельзя — для этого есть отдельная передача капитанства.
     */
    public function changeMemberTeamRole(EventTeam $team, EventTeamMember $member, string $newTeamRole, int $performedByUserId): EventTeamMember
    {
        if ((int) $member->event_team_id !== (int) $team->id) {
            throw new DomainException('Игрок не состоит в этой команде.');
        }

        if (!in_array($newTeamRole, ['player', 'reserve'], true)) {
            throw new DomainException('Недопустимая роль участника.');
        }

        if ($member->team_role === 'captain') {
            throw new DomainException('Капитана нельзя перевести в запасные напрямую — сначала передайте капитанство.');
        }

        if ($member->confirmation_status !== 'confirmed') {
            throw new DomainException('Роль можно менять только подтверждённым игрокам.');
        }

        $oldTeamRole = $member->team_role;
        if ($oldTeamRole === $newTeamRole) {
            return $member;
        }

        if ($newTeamRole === 'reserve') {
            $limits = $this->getTeamLimits($team);
            $currentReserves = $team->members
                ->where('confirmation_status', 'confirmed')
                ->where('team_role', 'reserve')
                ->count();

            if ($currentReserves >= (int) $limits['reserve_max']) {
                throw new DomainException("Достигнут лимит запасных игроков ({$limits['reserve_max']}).");
            }
        }

        return DB::transaction(function () use ($team, $member, $newTeamRole, $oldTeamRole, $performedByUserId) {
            $member->team_role = $newTeamRole;
            $member->save();

            $this->audit(
                team: $team,
                action: 'team_role_changed',
                userId: $member->user_id,
                performedByUserId: $performedByUserId,
                oldValue: ['team_role' => $oldTeamRole],
                newValue: ['team_role' => $newTeamRole],
            );

            $this->refreshTeamState($team->fresh());

            return $member->fresh();
        });
    }

    /**
     * Пригласить или добавить участника в команду
     */
    /**
     * Организатор/админ напрямую добавляет игрока в команду без invite-flow.
     * Игрок сразу confirmed, получает уведомление «Организатор добавил вас в команду».
     */
    public function addMemberByOrganizer(
        EventTeam $team,
        User $player,
        User $organizer,
        string $teamRole = 'player',
        ?string $positionCode = null
    ): EventTeamMember {
        $isAdmin     = ($organizer->role ?? null) === 'admin';
        $isEventOrg  = $team->event?->organizer_id
            ? (int) $team->event->organizer_id === (int) $organizer->id
            : false;

        if (!$isAdmin && !$isEventOrg) {
            throw new DomainException('Добавить игрока напрямую может только организатор мероприятия или администратор.');
        }

        // Используем inviteOrJoinMember с autoConfirm=true (содержит все проверки)
        $member = $this->inviteOrJoinMember(
            team: $team,
            user: $player,
            invitedByUserId: $organizer->id,
            teamRole: $teamRole,
            positionCode: $positionCode,
            autoConfirm: true,
        );

        // Уведомление игроку
        try {
            $event   = $team->event ?: $team->event()->first();
            $teamUrl = route('tournamentTeams.show', [$team->event_id, $team->id]);

            $isPair  = (string) $team->team_kind === 'beach_pair';
            $title   = __($isPair ? 'events.org_added_title_pair' : 'events.org_added_title_team');
            $body    = __('events.org_added_body', [
                'team'  => $team->name,
                'event' => $event?->title ?? '',
            ]);
            $bodyAction = __('events.tapp_action_open', ['url' => $teamUrl]);

            app(\App\Services\UserNotificationService::class)->create(
                userId: (int) $player->id,
                type:   'tournament_organizer_added',
                title:  $title,
                body:   $body . "\n\n" . $bodyAction,
                payload: [
                    'event_id'    => $team->event_id,
                    'team_id'     => $team->id,
                    'team_url'    => $teamUrl,
                    'button_text' => __('events.tinv_revoke_btn') !== '✕ Отозвать' ? 'Открыть' : 'Открыть',
                    'button_url'  => $teamUrl,
                ],
                channels: ['in_app', 'telegram', 'vk', 'max']
            );
        } catch (\Throwable $e) {
            report($e);
        }

        return $member;
    }

    public function inviteOrJoinMember(
        EventTeam $team,
        User $user,
        ?int $invitedByUserId = null,
        string $teamRole = 'player',
        ?string $positionCode = null,
        bool $autoConfirm = false
    ): EventTeamMember {
        return DB::transaction(function () use ($team, $user, $invitedByUserId, $teamRole, $positionCode, $autoConfirm) {
            $existing = EventTeamMember::query()
                ->where('event_team_id', $team->id)
                ->where('user_id', $user->id)
                ->first();

            if ($existing) {
                throw new DomainException('Пользователь уже есть в составе команды.');
            }

            // Проверка соответствия игрока требованиям мероприятия
            $event = $team->event ?: $team->event()->first();
            if ($event) {
                $issues = app(MemberEligibilityService::class)->checkMember($user, $event);
                if (!empty($issues)) {
                    throw new DomainException('Игрок не соответствует требованиям мероприятия: ' . implode('; ', $issues));
                }
            }

            if ($teamRole === 'captain') {
                $hasCaptain = EventTeamMember::query()
                    ->where('event_team_id', $team->id)
                    ->where('team_role', 'captain')
                    ->exists();
    
                if ($hasCaptain) {
                    throw new DomainException('В команде уже есть капитан.');
                }
                
                if (!$autoConfirm) {
                    throw new DomainException('Капитан должен добавляться сразу подтверждённым.');
                }
            }
    
            if ((string) $team->team_kind === 'beach_pair' && !empty($positionCode)) {
                throw new DomainException('Для пляжного волейбола позиция не используется.');
            }
    
            if ((string) $team->team_kind === 'classic_team') {
                if (in_array($teamRole, ['player', 'captain'], true) && empty($positionCode)) {
                    throw new DomainException('Для основного состава классического волейбола нужно указать амплуа.');
                }
    
                if (!empty($positionCode)) {
                    $allowedPositions = array_keys($this->getAvailablePositionOptions($team));
    
                    if (!in_array($positionCode, $allowedPositions, true)) {
                        throw new DomainException('Недопустимая позиция для этой схемы игры.');
                    }
                }
    
                if (!in_array($teamRole, ['player', 'captain'], true)) {
                    $positionCode = null;
                }
            }
    
            $limits = $this->getTeamLimits($team);
    
            $currentTotal = EventTeamMember::query()
                ->where('event_team_id', $team->id)
                ->whereIn('confirmation_status', ['invited', 'joined', 'confirmed'])
                ->count();
    
            if ($currentTotal >= (int) $limits['total_max']) {
                throw new DomainException("Достигнут максимальный размер команды ({$limits['total_max']})");
            }
    
            if ($teamRole === 'reserve') {
                $currentReserves = EventTeamMember::query()
                    ->where('event_team_id', $team->id)
                    ->where('team_role', 'reserve')
                    ->whereIn('confirmation_status', ['invited', 'joined', 'confirmed'])
                    ->count();
    
                if ($currentReserves >= (int) $limits['reserve_max']) {
                    throw new DomainException("Достигнут лимит запасных игроков ({$limits['reserve_max']})");
                }
            }
    
            $roleCode = $positionCode ?: $teamRole;
    
            $member = EventTeamMember::query()->create([
                'event_team_id' => $team->id,
                'user_id' => $user->id,
                'role_code' => $roleCode,
                'team_role' => $teamRole,
                'position_code' => $positionCode,
                'confirmation_status' => $autoConfirm ? 'confirmed' : 'joined',
                'position_order' => null,
                'invited_by_user_id' => $invitedByUserId,
                'joined_at' => now(),
                'responded_at' => now(),
                'confirmed_at' => $autoConfirm ? now() : null,
                'meta' => null,
            ]);
    
            $this->audit(
                team: $team,
                action: $autoConfirm ? 'member_confirmed' : 'member_joined',
                userId: $user->id,
                performedByUserId: $invitedByUserId,
                newValue: [
                    'member_id' => $member->id,
                    'role_code' => $roleCode,
                    'team_role' => $teamRole,
                    'position_code' => $positionCode,
                    'confirmation_status' => $member->confirmation_status,
                ],
            );
    
            $this->refreshTeamState($team->fresh());
    
            return $member;
        });
    }

    public function confirmMember(EventTeam $team, int $memberId, User $performedBy): EventTeam
    {
        return DB::transaction(function () use ($team, $memberId, $performedBy) {
            $member = EventTeamMember::query()
                ->where('event_team_id', $team->id)
                ->where('id', $memberId)
                ->first();

            if (!$member) {
                throw new DomainException('Участник команды не найден.');
            }

            $wasRequested = $member->confirmation_status === 'requested';

            $old = [
                'confirmation_status' => $member->confirmation_status,
                'confirmed_at' => $member->confirmed_at,
            ];

            $member->update([
                'confirmation_status' => 'confirmed',
                'responded_at' => now(),
                'confirmed_at' => now(),
            ]);

            $this->audit(
                team: $team,
                action: 'member_confirmed',
                userId: $member->user_id,
                performedByUserId: $performedBy->id,
                oldValue: $old,
                newValue: [
                    'confirmation_status' => 'confirmed',
                    'confirmed_at' => $member->confirmed_at,
                ],
            );

            // Если это был запрос на вступление — отклоняем остальные запросы
            if ($wasRequested) {
                $otherRequests = EventTeamMember::where('event_team_id', $team->id)
                    ->where('id', '!=', $member->id)
                    ->where('confirmation_status', 'requested')
                    ->get();

                foreach ($otherRequests as $other) {
                    $other->update(['confirmation_status' => 'declined', 'responded_at' => now()]);
                    $this->notifyUser(
                        userId: (int) $other->user_id,
                        type: 'team_join_declined',
                        title: 'Заявка на вступление отклонена',
                        body: "Место в паре «{$team->name}» занято другим игроком.",
                        payload: ['team_id' => $team->id, 'event_id' => $team->event_id],
                    );
                }

                // Уведомляем принятого игрока
                $this->notifyUser(
                    userId: (int) $member->user_id,
                    type: 'team_join_accepted',
                    title: 'Заявка на вступление принята',
                    body: "Капитан принял вашу заявку в пару «{$team->name}».",
                    payload: ['team_id' => $team->id, 'event_id' => $team->event_id],
                );
            }

            return $this->refreshTeamState($team->fresh());
        });
    }

    public function declineMember(EventTeam $team, int $memberId, User $performedBy): EventTeam
    {
        return DB::transaction(function () use ($team, $memberId, $performedBy) {
            $member = EventTeamMember::query()
                ->where('event_team_id', $team->id)
                ->where('id', $memberId)
                ->first();

            if (!$member) {
                throw new DomainException('Участник команды не найден.');
            }

            $wasRequested = $member->confirmation_status === 'requested';

            $old = [
                'confirmation_status' => $member->confirmation_status,
            ];

            $member->update([
                'confirmation_status' => 'declined',
                'responded_at' => now(),
            ]);

            $this->audit(
                team: $team,
                action: 'member_declined',
                userId: $member->user_id,
                performedByUserId: $performedBy->id,
                oldValue: $old,
                newValue: ['confirmation_status' => 'declined'],
            );

            if ($wasRequested) {
                $this->notifyUser(
                    userId: (int) $member->user_id,
                    type: 'team_join_declined',
                    title: 'Заявка на вступление отклонена',
                    body: "Капитан отклонил вашу заявку в пару «{$team->name}».",
                    payload: ['team_id' => $team->id, 'event_id' => $team->event_id],
                );
            }

            return $this->refreshTeamState($team->fresh());
        });
    }

    public function removeMember(EventTeam $team, int $memberId, User $performedBy): EventTeam
    {
        return DB::transaction(function () use ($team, $memberId, $performedBy) {
            $member = EventTeamMember::query()
                ->where('event_team_id', $team->id)
                ->where('id', $memberId)
                ->first();

            if (!$member) {
                throw new DomainException('Участник команды не найден.');
            }

            if ((int) $member->user_id === (int) $team->captain_user_id) {
                throw new DomainException('Нельзя удалить капитана из команды.');
            }

            $snapshot = [
                'member_id' => $member->id,
                'user_id' => $member->user_id,
                'role_code' => $member->role_code,
                'team_role' => $member->team_role,
                'position_code' => $member->position_code,
                'confirmation_status' => $member->confirmation_status,
            ];

            $member->delete();

            $this->audit(
                team: $team,
                action: 'member_removed',
                userId: $snapshot['user_id'],
                performedByUserId: $performedBy->id,
                oldValue: $snapshot,
            );

            return $this->refreshTeamState($team->fresh());
        });
    }
    
    public function submitApplication(EventTeam $team, User $submittedBy, bool $allowIncomplete = false): EventTeamApplication
    {
        $application = DB::transaction(function () use ($team, $submittedBy, $allowIncomplete) {
            $team = $team->fresh(['members.user', 'event.tournamentSetting', 'event.gameSettings']);

            if (!$allowIncomplete) {
                if (!$team->is_complete || $team->status !== 'ready') {
                    throw new DomainException('Команда ещё не готова к подаче заявки.');
                }
            } else {
                $eventSettings = \App\Models\EventTournamentSetting::where('event_id', $team->event_id)->first();
                if (!($eventSettings?->allow_incomplete_application)) {
                    throw new DomainException('Досрочная подача заявки на этом турнире не разрешена организатором.');
                }
                // Early submit: достаточно капитана
                $hasCaptain = (bool) $team->captain_user_id;
                if (!$hasCaptain) {
                    throw new DomainException('Для подачи заявки нужен капитан команды.');
                }
            }

            // === Проверка соответствия УЖЕ добавленных игроков требованиям мероприятия ===
            $eligibility = app(MemberEligibilityService::class);
            $issues = $eligibility->checkTeamMembers($team);
            if (!empty($issues)) {
                throw new DomainException('Не соответствует требованиям мероприятия: ' . implode('; ', $issues));
            }

            // === Гендерные ограничения команды (mixed_5050 / mixed_limited / max_rating_sum) ===
            // При early-submit пропускаем строгие правила состава, проверяем только лимиты на игрока (already in eligibility)
            if (!$allowIncomplete) {
                $this->validateTeamGender($team);
            }

            $existing = EventTeamApplication::query()
                ->where('event_team_id', $team->id)
                ->first();

            if ($existing) {
                throw new DomainException('Заявка уже существует.');
            }

            $settings = \App\Models\EventTournamentSetting::where('event_id', $team->event_id)->first();
            $isAutoApproval = ($settings->application_mode ?? 'manual') === 'auto';

            if ($allowIncomplete) {
                $applicationStatus = 'incomplete';
                $teamStatus = 'pending_members';
                $decisionComment = null;
            } else {
                $applicationStatus = $isAutoApproval ? 'approved' : 'pending';
                $teamStatus = $isAutoApproval ? 'submitted' : 'pending';
                $decisionComment = $isAutoApproval ? 'Автоматическое одобрение' : null;
            }

            $application = EventTeamApplication::query()->create([
                'event_id' => $team->event_id,
                'event_team_id' => $team->id,
                'status' => $applicationStatus,
                'submitted_by_user_id' => $submittedBy->id,
                'applied_at' => now(),
                'reviewed_by_user_id' => null,
                'reviewed_at' => (!$allowIncomplete && $isAutoApproval) ? now() : null,
                'rejection_reason' => null,
                'decision_comment' => $decisionComment,
                'meta' => $allowIncomplete ? ['allow_incomplete' => true] : null,
            ]);

            $team->update([
                'status' => $teamStatus,
            ]);

            $this->audit(
                team: $team,
                action: $allowIncomplete ? 'application_submitted_incomplete' : 'application_submitted',
                performedByUserId: $submittedBy->id,
                newValue: [
                    'application_id' => $application->id,
                    'status' => $applicationStatus,
                ],
            );

            return $application;
        });

        // Уведомление организатору (после коммита транзакции).
        // - 'pending'    — обычная заявка с полным составом, ждёт одобрения
        // - 'incomplete' — early-submit: команда ещё доукомплектовывается
        if (in_array($application->status, ['pending', 'incomplete'], true)) {
            try {
                $event = $team->event ?: $team->event()->first();
                $organizerId = (int) $event->organizer_id;
                $setupUrl = route('tournament.setup', $event);

                $isIncomplete = $application->status === 'incomplete';
                $type = $isIncomplete ? 'tournament_application_incomplete' : 'tournament_application_received';
                $title = $isIncomplete
                    ? __('events.tapp_incomplete_title')
                    : __('events.tapp_received_title');
                $body = $isIncomplete
                    ? __('events.tapp_incomplete_body', ['team' => $team->name, 'event' => $event->title])
                    : __('events.tapp_received_body', ['team' => $team->name, 'event' => $event->title]);

                app(\App\Services\UserNotificationService::class)->create(
                    userId: $organizerId,
                    type: $type,
                    title: $title,
                    body: $body . "\n\n" . __('events.tapp_action_open', ['url' => $setupUrl]),
                    payload: [
                        'event_id' => $event->id,
                        'team_id' => $team->id,
                        'application_id' => $application->id,
                        'team_name' => $team->name,
                        'event_title' => $event->title,
                        'button_text' => __('events.tapp_btn_manage'),
                        'button_url' => $setupUrl,
                    ],
                    channels: ['in_app', 'telegram', 'vk', 'max']
                );
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $application;
    }

    /**
     * Отзыв заявки капитаном или админом.
     * Доступно для статусов 'incomplete' и 'pending'.
     */
    public function revokeApplication(EventTeam $team, User $byUser): void
    {
        DB::transaction(function () use ($team, $byUser) {
            $application = EventTeamApplication::query()
                ->where('event_team_id', $team->id)
                ->lockForUpdate()
                ->first();

            if (!$application) {
                throw new DomainException('Заявка не найдена.');
            }

            $isCaptain = (int) $team->captain_user_id === (int) $byUser->id;
            $isAdmin = ($byUser->role ?? null) === 'admin';

            if (!$isCaptain && !$isAdmin) {
                throw new DomainException('Отозвать заявку может только капитан команды.');
            }

            if (!in_array($application->status, ['incomplete', 'pending'], true)) {
                throw new DomainException('Можно отозвать только активную заявку (ожидающую решения).');
            }

            $application->delete();

            // Возвращаем команду в одно из состояний до подачи заявки
            $check = $this->checkRequirements($team->fresh(['members.user']));
            $newStatus = $check['valid']
                ? 'ready'
                : ($check['has_pending_members'] ? 'pending_members' : 'draft');
            $team->update(['status' => $newStatus]);

            $this->audit(
                team: $team,
                action: 'application_revoked',
                performedByUserId: $byUser->id,
                newValue: ['team_status' => $newStatus],
            );
        });
    }

    public function refreshTeamState(EventTeam $team): EventTeam
    {
        $team->loadMissing(['event.tournamentSetting', 'members.user']);

        $check = $this->checkRequirements($team);

        $nextStatus = $team->status;

        if ($team->status === 'draft' || $team->status === 'pending_members' || $team->status === 'ready') {
            if ($check['valid']) {
                $nextStatus = 'ready';
            } else {
                $nextStatus = $check['has_pending_members'] ? 'pending_members' : 'draft';
            }
        }

        $team->update([
            'is_complete' => $check['valid'],
            'status' => $nextStatus,
            'last_checked_at' => now(),
        ]);

        $settings = $this->getSettings($team->event);

        // Auto-submit при сборе ready-команды (если включено в настройках)
        if (
            $settings?->auto_submit_when_ready &&
            $team->fresh()->status === 'ready' &&
            !$team->application()->exists()
        ) {
            $this->submitApplication($team->fresh(), $team->captain);
        }

        // Auto-submit early: оба флага включены, application нет, есть капитан →
        // подаём «неполную» заявку автоматически. Если состав потом соберётся —
        // promoteIncompleteApplication сама переведёт в pending/approved.
        // Пропускаем для approved/submitted/rejected — органайзер уже принял решение.
        if (
            $settings?->auto_submit_when_ready &&
            $settings?->allow_incomplete_application &&
            !$team->fresh()->application()->exists() &&
            $team->captain_user_id &&
            !in_array($team->status, ['approved', 'submitted', 'rejected'], true)
        ) {
            try {
                $this->submitApplication($team->fresh(), $team->captain, true);
            } catch (DomainException $e) {
                // Eligibility issues / другие — оставляем команду в текущем статусе
            }
        }

        // Auto-promotion: если есть incomplete-заявка и команда стала valid →
        // переводим заявку в pending/approved (зависит от application_mode)
        if ($check['valid']) {
            $existingApp = EventTeamApplication::query()
                ->where('event_team_id', $team->id)
                ->where('status', 'incomplete')
                ->first();
            if ($existingApp) {
                $this->promoteIncompleteApplication($team->fresh(), $existingApp);
            }
        }

        return $team->fresh(['members.user', 'application', 'event.tournamentSetting']);
    }

    /**
     * Переводит incomplete-заявку в активное состояние, когда команда укомплектована.
     */
    private function promoteIncompleteApplication(EventTeam $team, EventTeamApplication $application): void
    {
        // Финальная проверка eligibility / gender
        $eligibility = app(MemberEligibilityService::class);
        $issues = $eligibility->checkTeamMembers($team);
        if (!empty($issues)) {
            return; // нельзя promote, оставляем incomplete
        }
        try {
            $this->validateTeamGender($team);
        } catch (DomainException $e) {
            return;
        }

        $settings = \App\Models\EventTournamentSetting::where('event_id', $team->event_id)->first();
        $isAutoApproval = ($settings->application_mode ?? 'manual') === 'auto';

        $newAppStatus = $isAutoApproval ? 'approved' : 'pending';
        $newTeamStatus = $isAutoApproval ? 'submitted' : 'pending';

        DB::transaction(function () use ($team, $application, $newAppStatus, $newTeamStatus, $isAutoApproval) {
            $application->update([
                'status' => $newAppStatus,
                'reviewed_at' => $isAutoApproval ? now() : null,
                'decision_comment' => $isAutoApproval ? 'Автоматическое одобрение после доукомплектования' : null,
            ]);
            $team->update(['status' => $newTeamStatus]);

            $this->audit(
                team: $team,
                action: 'application_completed',
                newValue: ['application_id' => $application->id, 'status' => $newAppStatus],
            );
        });

        // Уведомление организатору в manual-режиме
        if (!$isAutoApproval) {
            try {
                $event = $team->event ?: $team->event()->first();
                $setupUrl = route('tournament.setup', $event);
                app(\App\Services\UserNotificationService::class)->create(
                    userId: (int) $event->organizer_id,
                    type: 'tournament_application_completed',
                    title: __('events.tapp_completed_title'),
                    body: __('events.tapp_completed_body', ['team' => $team->name, 'event' => $event->title])
                        . "\n\n" . __('events.tapp_action_open', ['url' => $setupUrl]),
                    payload: [
                        'event_id' => $event->id,
                        'team_id' => $team->id,
                        'application_id' => $application->id,
                        'team_name' => $team->name,
                        'event_title' => $event->title,
                        'button_text' => __('events.tapp_btn_manage'),
                        'button_url' => $setupUrl,
                    ],
                    channels: ['in_app', 'telegram', 'vk', 'max']
                );
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    public function checkRequirements(EventTeam $team): array
    {
        $team->loadMissing(['event.tournamentSetting', 'members.user']);
    
        $settings = $this->getSettings($team->event);
        $limits = $this->getTeamLimits($team);
    
        $members = $team->members;
        $confirmed = $members->where('confirmation_status', 'confirmed');
        $pending = $members->whereIn('confirmation_status', ['invited', 'joined']);
    
        $reserves = $confirmed->where('team_role', 'reserve');
        $players = $confirmed->where('team_role', 'player');
        $captain = $confirmed->firstWhere('team_role', 'captain');
    
        $issues = [];
    
        if ($team->team_kind === 'classic_team') {
            $scheme = $settings?->getGameScheme() ?? '5x1';
            $requiredPositions = (array) config("volleyball.classic.{$scheme}.positions", []);
    
            foreach ($requiredPositions as $position => $requiredCount) {
                $currentCount = $confirmed->where('position_code', $position)->count();
    
                if ($currentCount < (int) $requiredCount) {
                    $issues[] = "Недостаточно {$this->positionLabel($position)}: {$currentCount} из {$requiredCount}";
                }
            }
    
            if ($settings?->require_libero) {
                $hasLibero = $confirmed->contains('position_code', 'libero');
                if (!$hasLibero) {
                    $issues[] = 'В составе отсутствует либеро';
                }
            }
        }
    
        $reserveCount = $reserves->count();
        if ($reserveCount > (int) $limits['reserve_max']) {
            $issues[] = "Слишком много запасных: {$reserveCount} из {$limits['reserve_max']}";
        }
    
        $totalConfirmed = $confirmed->count();
        if ($totalConfirmed > (int) $limits['total_max']) {
            $issues[] = "Слишком много игроков: {$totalConfirmed} из {$limits['total_max']}";
        }
    
        $playersCount = $players->count() + ($captain ? 1 : 0);
        if ($playersCount < (int) $limits['min_players']) {
            $issues[] = "Недостаточно основных игроков: {$playersCount} из {$limits['min_players']}";
        }
    
        if (!$captain) {
            $issues[] = 'В команде должен быть капитан';
        }
    
        if ($team->team_kind === 'beach_pair' && $settings?->max_rating_sum) {
            $totalRating = $confirmed->sum(fn ($m) => (int) ($m->user->rating ?? 0));
    
            if ($totalRating > (int) $settings->max_rating_sum) {
                $issues[] = "Сумма рейтингов ({$totalRating}) превышает лимит ({$settings->max_rating_sum})";
            }
        }
    
        return [
            'valid' => count($issues) === 0,
            'issues' => $issues,
            'confirmed_count' => $totalConfirmed,
            'players_count' => $playersCount,
            'reserve_count' => $reserveCount,
            'pending_count' => $pending->count(),
            'has_pending_members' => $pending->count() > 0,
            'limits' => $limits,
        ];
    }
    
    public function getTeamLimits(EventTeam $team): array
    {
        $settings = $this->getSettings($team->event);
    
        $scheme = $settings?->getGameScheme()
            ?? ($team->team_kind === 'beach_pair' ? '2x2' : '5x1');
    
        if ($team->team_kind === 'beach_pair') {
            $base = match ($scheme) {
                '2x2' => ['min_players' => 2, 'reserve_max' => 0, 'total_max' => 2],
                '3x3' => ['min_players' => 3, 'reserve_max' => 2, 'total_max' => 5],
                '4x4' => ['min_players' => 4, 'reserve_max' => 3, 'total_max' => 7],
                default => ['min_players' => 2, 'reserve_max' => 0, 'total_max' => 2],
            };
        } else {
            $base = match ($scheme) {
                '4x4' => ['min_players' => 4, 'reserve_max' => 3, 'total_max' => 7],
                '4x2' => ['min_players' => 6, 'reserve_max' => 4, 'total_max' => 10],
                '5x1' => ['min_players' => 6, 'reserve_max' => 4, 'total_max' => 10],
                '5x1_libero' => ['min_players' => 7, 'reserve_max' => 4, 'total_max' => 11],
                default => ['min_players' => 6, 'reserve_max' => 4, 'total_max' => 10],
            };
        }
    
        if ($settings) {
            if (!is_null($settings->team_size_min)) {
                $base['min_players'] = (int) $settings->team_size_min;
            }
    
            if (!is_null($settings->getReserveMax())) {
                $base['reserve_max'] = (int) $settings->getReserveMax();
            }
    
            if (!is_null($settings->getTotalMax())) {
                $base['total_max'] = (int) $settings->getTotalMax();
            } elseif (!is_null($settings->team_size_max)) {
                $base['total_max'] = (int) $settings->team_size_max;
            }
        }
    
        return $base;
    }
    
    private function getSettings(Event $event): ?EventTournamentSetting
    {
        return $event->relationLoaded('tournamentSetting')
            ? $event->tournamentSetting
            : $event->tournamentSetting()->first();
    }

    private function resolveTeamKindFromSettings(?EventTournamentSetting $settings): string
    {
        return match ($settings?->registration_mode) {
            'team_beach' => 'beach_pair',
            default => 'classic_team',
        };
    }

    private function generateInviteCode(): string
    {
        do {
            $code = Str::upper(Str::random(10));
        } while (EventTeam::query()->where('invite_code', $code)->exists());

        return $code;
    }

    private function audit(
        EventTeam $team,
        string $action,
        ?int $userId = null,
        ?int $performedByUserId = null,
        ?array $oldValue = null,
        ?array $newValue = null,
        ?array $meta = null
    ): void {
        EventTeamMemberAudit::query()->create([
            'event_team_id' => $team->id,
            'user_id' => $userId,
            'action' => $action,
            'performed_by_user_id' => $performedByUserId,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'meta' => $meta,
            'created_at' => now(),
        ]);
    }

    /**
     * Проверяет, что все члены команды соответствуют требованиям по уровню турнира.
     */
    private function validateTeamMembersLevel(EventTeam $team): void
    {
        $event = $team->event;
        if (!$event) return;

        $direction = $event->direction ?? 'classic';

        if ($direction === 'beach') {
            $lvMin = $event->beach_level_min;
            $lvMax = $event->beach_level_max;
            $levelField = 'beach_level';
        } else {
            $lvMin = $event->classic_level_min;
            $lvMax = $event->classic_level_max;
            $levelField = 'classic_level';
        }

        // Если ограничений нет — пропускаем
        if (is_null($lvMin) && is_null($lvMax)) return;

        $members = $team->members()
            ->whereIn('confirmation_status', ['confirmed', 'self'])
            ->with('user')
            ->get();

        $violations = [];

        foreach ($members as $member) {
            $user = $member->user;
            if (!$user) continue;

            $userLevel = $user->{$levelField};

            // Если у игрока не указан уровень — пропускаем (или можно блокировать)
            if (is_null($userLevel)) continue;

            $tooLow  = !is_null($lvMin) && $userLevel < $lvMin;
            $tooHigh = !is_null($lvMax) && $userLevel > $lvMax;

            if ($tooLow || $tooHigh) {
                $name = trim(($user->last_name ?? '') . ' ' . ($user->first_name ?? ''));
                if ($name === '') $name = $user->name ?: "Игрок #{$user->id}";
                $violations[] = $name;
            }
        }

        if (!empty($violations)) {
            $captain = $team->captain;
            $captainName = trim(($captain->last_name ?? '') . ' ' . ($captain->first_name ?? ''));
            if ($captainName === '') $captainName = $captain->name ?? 'Капитан';

            $playersList = implode(', ', $violations);

            throw new DomainException(
                "Уважаемый {$captainName}, участник(и): {$playersList} не соответствуют требованиям по уровню! "
                . "Свяжитесь с организатором или замените игроков."
            );
        }
    }


    /**
     * Игрок покидает команду (сам). Рефанд если per_player.
     * Для beach_pair: если капитан уходит — передаёт капитанство второму игроку.
     */
    public function leaveTeam(EventTeam $team, int $userId): void
    {
        $member = $team->members()->where('user_id', $userId)->first();
        if (!$member) {
            throw new DomainException('Вы не состоите в этой команде.');
        }

        // Проверяем дедлайн самостоятельного выхода
        $occurrence = $team->occurrence;
        $cancelUntil = $occurrence?->effectiveCancelSelfUntil();
        if ($cancelUntil && now('UTC')->greaterThan($cancelUntil)) {
            throw new DomainException('Срок самостоятельного выхода из команды истёк. Обратитесь к организатору.');
        }

        $isCaptain = (int) $team->captain_user_id === $userId;
        $event = $team->event;

        if ($isCaptain) {
            if ($team->team_kind !== 'beach_pair') {
                throw new DomainException('Капитан не может покинуть команду. Используйте «Расформировать команду».');
            }

            // Beach pair: передаём капитанство второму подтверждённому игроку
            $newCaptainMember = $team->members()
                ->where('user_id', '!=', $userId)
                ->where('confirmation_status', 'confirmed')
                ->orderBy('position_order')
                ->first();

            if (!$newCaptainMember) {
                // Нет другого участника — расформировываем
                $this->disbandTeam($team, $userId);
                return;
            }

            // Если у партнёра уже есть своя команда на этом турнире — расформировываем,
            // иначе он окажется в двух командах одновременно
            $partnerHasOtherTeam = EventTeam::query()
                ->where('id', '!=', $team->id)
                ->where('event_id', $team->event_id)
                ->where('occurrence_id', $team->occurrence_id)
                ->whereHas('members', fn ($q) => $q->where('user_id', $newCaptainMember->user_id))
                ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
                ->exists();

            if ($partnerHasOtherTeam) {
                $this->disbandTeam($team, $userId);
                return;
            }

            DB::transaction(function () use ($team, $member, $newCaptainMember, $event, $userId) {
                $this->refundForMember($member, $event);
                $member->delete();

                $team->update(['captain_user_id' => $newCaptainMember->user_id]);
                $newCaptainMember->update([
                    'role_code' => 'captain',
                    'team_role' => 'captain',
                ]);

                $this->audit($team, 'captain_transferred', $newCaptainMember->user_id, $userId, [], [
                    'reason' => 'previous_captain_left',
                    'new_captain_user_id' => $newCaptainMember->user_id,
                ]);

                $this->suspendApplicationIfNeeded($team);
                $this->recheckTeamCompleteness($team);

                $this->notifyUser(
                    userId: (int) $newCaptainMember->user_id,
                    type: 'team_captain_transferred',
                    title: 'Вы стали капитаном',
                    body: "Капитан покинул пару «{$team->name}». Теперь вы капитан.",
                    payload: ['team_id' => $team->id, 'event_id' => $event->id],
                );
            });

            return;
        }

        // Обычный участник покидает команду
        DB::transaction(function () use ($team, $member, $event) {
            $this->refundForMember($member, $event);
            $member->delete();

            $this->suspendApplicationIfNeeded($team);
            $this->recheckTeamCompleteness($team);

            $this->notifyUser(
                userId: (int) $team->captain_user_id,
                type: 'team_member_left',
                title: 'Игрок покинул команду',
                body: "Игрок покинул команду «{$team->name}».",
                payload: ['team_id' => $team->id, 'event_id' => $event->id],
            );
        });
    }

    /**
     * Капитан вручную передаёт капитанство подтверждённому участнику команды.
     */
    public function transferCaptain(EventTeam $team, int $newCaptainUserId, int $performedByUserId): void
    {
        if ((int) $team->captain_user_id !== $performedByUserId) {
            throw new DomainException('Передать капитанство может только текущий капитан.');
        }

        if ($newCaptainUserId === $performedByUserId) {
            throw new DomainException('Вы уже являетесь капитаном.');
        }

        $newCaptainMember = $team->members()
            ->where('user_id', $newCaptainUserId)
            ->where('confirmation_status', 'confirmed')
            ->first();

        if (!$newCaptainMember) {
            throw new DomainException('Игрок не найден в составе или не подтверждён.');
        }

        $oldCaptainMember = $team->members()
            ->where('user_id', $performedByUserId)
            ->first();

        DB::transaction(function () use ($team, $newCaptainMember, $oldCaptainMember, $newCaptainUserId, $performedByUserId) {
            $team->update(['captain_user_id' => $newCaptainUserId]);

            $newCaptainMember->update([
                'role_code' => 'captain',
                'team_role' => 'captain',
            ]);

            if ($oldCaptainMember) {
                $oldCaptainMember->update([
                    'role_code' => 'player',
                    'team_role' => 'player',
                ]);
            }

            $this->audit($team, 'captain_transferred', $newCaptainUserId, $performedByUserId, [], [
                'reason' => 'manual_transfer',
                'new_captain_user_id' => $newCaptainUserId,
            ]);

            $this->notifyUser(
                userId: $newCaptainUserId,
                type: 'team_captain_transferred',
                title: 'Вы стали капитаном',
                body: "Вам передано капитанство в команде «{$team->name}».",
                payload: ['team_id' => $team->id, 'event_id' => $team->event_id],
            );
        });
    }

    /**
     * Запрос игрока на вступление в пару (beach_pair с вакантным местом).
     */
    public function joinRequest(EventTeam $team, User $user): EventTeamMember
    {
        if ($team->team_kind !== 'beach_pair') {
            throw new DomainException('Запросы на вступление доступны только для пляжных пар.');
        }

        $confirmedCount = $team->members()->where('confirmation_status', 'confirmed')->count();
        if ($confirmedCount >= 2) {
            throw new DomainException('В паре нет свободного места.');
        }

        // Проверяем дедлайн
        $occurrence = $team->occurrence;
        $cancelUntil = $occurrence?->effectiveCancelSelfUntil();
        if ($cancelUntil && now('UTC')->greaterThan($cancelUntil)) {
            throw new DomainException('Срок подачи заявок на вступление в пару истёк.');
        }

        // Не должен уже быть в этой команде
        $alreadyHere = $team->members()
            ->where('user_id', $user->id)
            ->whereIn('confirmation_status', ['confirmed', 'joined', 'requested'])
            ->exists();
        if ($alreadyHere) {
            throw new DomainException('Вы уже подали заявку или состоите в этой паре.');
        }

        // Соответствие игрока требованиям мероприятия
        $event = $team->event ?: $team->event()->first();
        if ($event) {
            $issues = app(MemberEligibilityService::class)->checkMember($user, $event);
            if (!empty($issues)) {
                throw new DomainException('Вы не соответствуете требованиям мероприятия: ' . implode('; ', $issues));
            }
        }

        // Не должен быть в другой активной команде на этот конкретный тур
        $alreadyInTeam = EventTeamMember::whereHas('team', function ($q) use ($team) {
            $q->where('event_id', $team->event_id)
              ->where('occurrence_id', $team->occurrence_id)
              ->where('id', '!=', $team->id)
              ->whereIn('status', ['ready', 'pending_members', 'submitted', 'confirmed', 'approved']);
        })
        ->where('user_id', $user->id)
        ->whereIn('confirmation_status', ['confirmed', 'joined'])
        ->exists();

        if ($alreadyInTeam) {
            throw new DomainException('Вы уже состоите в другой команде на это мероприятие.');
        }

        return DB::transaction(function () use ($team, $user) {
            $member = EventTeamMember::create([
                'event_team_id' => $team->id,
                'user_id' => $user->id,
                'role_code' => 'player',
                'team_role' => 'player',
                'confirmation_status' => 'requested',
                'position_order' => 2,
                'invited_by_user_id' => $user->id,
                'joined_at' => now(),
                'meta' => null,
            ]);

            $this->audit($team, 'join_requested', $user->id, $user->id, [], [
                'confirmation_status' => 'requested',
            ]);

            $memberName = trim(($user->last_name ?? '') . ' ' . ($user->first_name ?? '')) ?: $user->name;

            $isPair = (string) $team->team_kind === 'beach_pair';
            $teamUrl = route('tournamentTeams.show', [$team->event_id, $team->id]);
            $title = __($isPair ? 'events.tjr_title_pair' : 'events.tjr_title_team');
            $bodyMain = __(
                $isPair ? 'events.tjr_body_pair' : 'events.tjr_body_team',
                ['name' => $memberName, 'team' => $team->name]
            );
            $bodyAction = __('events.tjr_body_action', ['url' => $teamUrl]);

            $this->notifyUser(
                userId: (int) $team->captain_user_id,
                type: 'team_join_request',
                title: $title,
                body: $bodyMain . "\n\n" . $bodyAction,
                payload: [
                    'team_id'     => $team->id,
                    'event_id'    => $team->event_id,
                    'team_url'    => $teamUrl,
                    'button_text' => __('events.tjr_btn_open_team'),
                    'button_url'  => $teamUrl,
                ],
            );

            return $member;
        });
    }

    /**
     * Приостанавливает заявку команды на турнир если команда стала неполной.
     */
    private function suspendApplicationIfNeeded(EventTeam $team): void
    {
        EventTeamApplication::where('event_team_id', $team->id)
            ->whereIn('status', ['pending', 'approved'])
            ->update(['status' => 'incomplete']);
    }

    /**
     * Универсальный хелпер для отправки уведомлений.
     */
    private function notifyUser(int $userId, string $type, string $title, string $body, array $payload = []): void
    {
        try {
            app(\App\Services\UserNotificationService::class)->create(
                userId: $userId,
                type: $type,
                title: $title,
                body: $body,
                payload: $payload,
                channels: ['in_app', 'telegram', 'vk', 'max']
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Капитан расформировывает команду. Рефанд всем.
     */
    public function disbandTeam(EventTeam $team, int $captainUserId, bool $force = false): void
    {
        if (!$force && (int) $team->captain_user_id !== $captainUserId) {
            throw new DomainException('Только капитан может расформировать команду.');
        }

        $event = $team->event;
        $members = $team->members()->with('user')->get();
        $teamName = $team->name;
        $occurrenceId = $team->occurrence_id ? (int) $team->occurrence_id : null;

        // Рефанд
        $this->refundForTeam($team, $event);

        // Удаляем заявку
        \App\Models\EventTeamApplication::where('event_team_id', $team->id)->delete();
        // Удаляем приглашения
        \App\Models\EventTeamInvite::where('event_team_id', $team->id)->delete();
        // Удаляем членов
        $team->members()->delete();

        // Запоминаем данные для продвижения резерва ДО удаления команды
        $wasMainSlot = $team->reserve_position === null && !$event->season_id && $occurrenceId !== null;
        $disbandedEventId = $event->id;
        $disbandedOccId   = $occurrenceId ?? (int) $team->occurrence_id;

        // Удаляем команду
        $team->delete();

        // Продвигаем резерв если освободился основной слот
        if ($wasMainSlot && $disbandedOccId) {
            $this->fillFromEventReserve($disbandedEventId, $disbandedOccId);
        }

        // Уведомляем всех участников
        foreach ($members as $m) {
            if ((int) $m->user_id === $captainUserId) continue;
            try {
                app(\App\Services\UserNotificationService::class)->create(
                    userId: (int) $m->user_id,
                    type: 'team_disbanded',
                    title: 'Команда расформирована',
                    body: "Команда «{$teamName}» была расформирована капитаном.",
                    payload: ['event_id' => $event->id],
                    channels: ['in_app', 'telegram', 'vk', 'max']
                );
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    /**
     * Рефанд для одного участника (per_player mode).
     */
    private function refundForMember(\App\Models\EventTeamMember $member, \App\Models\Event $event): void
    {
        $payment = \App\Models\Payment::where('team_member_id', $member->id)
            ->whereIn('status', ['paid', 'confirmed'])
            ->first();

        if (!$payment) return;

        $paymentService = app(\App\Services\PaymentService::class);
        $refundAmount = $paymentService->calculateRefundAmount($payment, $event);

        if ($refundAmount > 0) {
            $paymentService->refund($payment, 'player_left_team', $refundAmount);
        }
    }

    /**
     * Рефанд для всей команды (team mode → капитану, per_player → каждому).
     */
    private function refundForTeam(EventTeam $team, \App\Models\Event $event): void
    {
        $settings = $event->tournamentSetting;
        $mode = $settings?->paymentMode() ?? 'team';

        if ($mode === 'per_player') {
            // Каждому участнику
            $members = $team->members()->get();
            foreach ($members as $m) {
                $this->refundForMember($m, $event);
            }
        } else {
            // Капитану за всю команду
            $payment = \App\Models\Payment::where('team_id', $team->id)
                ->whereIn('status', ['paid', 'confirmed'])
                ->first();

            if (!$payment) return;

            $paymentService = app(\App\Services\PaymentService::class);
            $refundAmount = $paymentService->calculateRefundAmount($payment, $event);

            if ($refundAmount > 0) {
                $paymentService->refund($payment, 'team_disbanded', $refundAmount);
            }
        }
    }

    /**
     * Пересчитать готовность команды после ухода игрока.
     */
    private function recheckTeamCompleteness(EventTeam $team): void
    {
        $team->refresh();
        $confirmedCount = $team->members()
            ->where('confirmation_status', 'confirmed')
            ->count();

        $settings = $team->event->tournamentSetting;
        $minPlayers = $settings?->team_size_min ?? 2;

        $team->update([
            'is_complete' => $confirmedCount >= $minPlayers,
        ]);
    }


    /**
     * Проверка гендерных ограничений команды.
     */
    private function validateTeamGender(EventTeam $team): void
    {
        $event = $team->event;
        $gameSettings = \App\Models\EventGameSetting::where('event_id', $event->id)->first();
        if (!$gameSettings) return;

        $policy = $gameSettings->gender_policy ?? 'mixed_open';
        if ($policy === 'mixed_open') return;

        $members = $team->members()
            ->whereIn('confirmation_status', ['confirmed', 'self'])
            ->with('user')
            ->get();

        $males = $members->filter(fn($m) => ($m->user->gender ?? null) === 'm')->count();
        $females = $members->filter(fn($m) => ($m->user->gender ?? null) === 'f')->count();
        $unknown = $members->filter(fn($m) => !in_array($m->user->gender ?? null, ['m', 'f']))->count();
        $total = $members->count();

        if ($unknown > 0) {
            $noGender = $members->filter(fn($m) => !in_array($m->user->gender ?? null, ['m', 'f']))
                ->map(fn($m) => trim(($m->user->last_name ?? '') . ' ' . ($m->user->first_name ?? '')) ?: "Игрок #{$m->user_id}")
                ->implode(', ');
            throw new DomainException("У следующих игроков не указан пол: {$noGender}. Укажите пол в профиле.");
        }

        switch ($policy) {
            case 'only_male':
                if ($females > 0) {
                    throw new DomainException('Этот турнир только для мужчин. В команде есть женщины.');
                }
                break;

            case 'only_female':
                if ($males > 0) {
                    throw new DomainException('Этот турнир только для женщин. В команде есть мужчины.');
                }
                break;

            case 'mixed_5050':
                $expectedPerGender = (int) floor($total / 2);
                if ($males !== $expectedPerGender || $females !== $expectedPerGender) {
                    throw new DomainException(
                        "Микс 50/50: нужно {$expectedPerGender}М + {$expectedPerGender}Ж. " .
                        "Сейчас: {$males}М + {$females}Ж."
                    );
                }
                break;

            case 'mixed_limited':
                $limitedSide = $gameSettings->gender_limited_side ?? 'f';
                $limitedMax = $gameSettings->gender_limited_max ?? 1;
                $count = $limitedSide === 'f' ? $females : $males;
                $label = $limitedSide === 'f' ? 'женщин' : 'мужчин';
                if ($count > $limitedMax) {
                    throw new DomainException("Ограничение: максимум {$limitedMax} {$label} в команде. Сейчас: {$count}.");
                }
                break;
        }
    }

    // ──────────────────────────────────────────────────────
    // Резерв командного турнира (не-лигового)
    // ──────────────────────────────────────────────────────

    /**
     * Есть ли свободные основные слоты в этом occurrence?
     */
    public function eventTournamentIsFull(Event $event, int $occurrenceId): bool
    {
        $max = (int) ($event->tournament_teams_count ?? 0);
        if ($max <= 0) {
            return false;
        }
        $main = EventTeam::where('event_id', $event->id)
            ->where('occurrence_id', $occurrenceId)
            ->whereNull('reserve_position')
            ->whereIn('status', ['draft', 'ready', 'pending_members', 'submitted', 'confirmed', 'approved'])
            ->count();
        return $main >= $max;
    }

    /**
     * Следующая позиция резерва для этого occurrence.
     */
    private function nextEventReservePosition(int $eventId, int $occurrenceId): int
    {
        $max = EventTeam::where('event_id', $eventId)
            ->where('occurrence_id', $occurrenceId)
            ->whereNotNull('reserve_position')
            ->max('reserve_position');
        return ($max ?? 0) + 1;
    }

    /**
     * Пересчитать reserve_position (1,2,3…) по порядку.
     */
    public function reindexEventReserve(int $eventId, int $occurrenceId): void
    {
        $teams = EventTeam::where('event_id', $eventId)
            ->where('occurrence_id', $occurrenceId)
            ->whereNotNull('reserve_position')
            ->orderBy('reserve_position')
            ->get();

        foreach ($teams as $i => $t) {
            $t->update(['reserve_position' => $i + 1]);
        }
    }

    /**
     * Продвинуть команды из резерва в основной состав если есть слоты.
     */
    public function fillFromEventReserve(int $eventId, int $occurrenceId): void
    {
        $event = Event::find($eventId);
        if (!$event || $event->season_id) {
            return; // Лиговые турниры используют TournamentLeagueService
        }

        $max = (int) ($event->tournament_teams_count ?? 0);
        if ($max <= 0) {
            return;
        }

        $mainCount = EventTeam::where('event_id', $eventId)
            ->where('occurrence_id', $occurrenceId)
            ->whereNull('reserve_position')
            ->whereIn('status', ['draft', 'ready', 'pending_members', 'submitted', 'confirmed', 'approved'])
            ->count();

        $available = $max - $mainCount;
        if ($available <= 0) {
            return;
        }

        // Берём первых N из резерва кому ещё не предложили место
        $candidates = EventTeam::where('event_id', $eventId)
            ->where('occurrence_id', $occurrenceId)
            ->whereNotNull('reserve_position')
            ->whereNull('confirmation_token')
            ->whereIn('status', ['draft', 'submitted'])
            ->orderBy('reserve_position')
            ->limit($available)
            ->get();

        foreach ($candidates as $team) {
            $this->offerEventReserveSpot($team);
        }
    }

    /**
     * Предложить команде место: отправить уведомление + поставить таймер 2ч.
     */
    public function offerEventReserveSpot(EventTeam $team): void
    {
        $token   = Str::random(64);
        $expires = Carbon::now()->addHours(2);

        $team->update([
            'confirmation_token'    => $token,
            'confirmation_expires_at' => $expires,
        ]);

        // Уведомление капитану
        $event     = $team->event;
        $occurrence = $team->occurrence;
        $confirmUrl = route('tournamentTeams.reserveConfirm', ['event' => $event->id, 'team' => $team->id, 'token' => $token]);
        $declineUrl = route('tournamentTeams.reserveDecline', ['event' => $event->id, 'team' => $team->id]);
        $expiresStr = $expires->format('d.m.Y H:i');

        try {
            app(UserNotificationService::class)->create(
                userId:   (int) $team->captain_user_id,
                type:     'team_reserve_spot_offered',
                title:    'Место в турнире для вашей команды!',
                body:     "Для вашей команды «{$team->name}» освободилось место в турнире «{$event->title}». "
                    . "Подтвердите участие до {$expiresStr}. "
                    . "Если не подтвердите — место перейдёт следующей команде.",
                payload:  [
                    'event_id'    => $event->id,
                    'team_id'     => $team->id,
                    'team_name'   => $team->name,
                    'expires_at'  => $expiresStr,
                    'confirm_url' => $confirmUrl,
                    'decline_url' => $declineUrl,
                    'button_text' => 'Подтвердить участие',
                    'button_url'  => $confirmUrl,
                ],
                channels: ['in_app', 'telegram', 'vk', 'max'],
            );
        } catch (\Throwable $e) {
            report($e);
        }

        // Job на истечение через 2 часа + 1 минута (небольшой буфер)
        ExpireEventTeamReserveJob::dispatch($team->id, $token)->delay($expires->addMinute());
    }

    /**
     * Капитан подтверждает место из резерва.
     */
    public function confirmEventReserveSpot(EventTeam $team, string $token): void
    {
        if ($team->confirmation_token !== $token) {
            throw new DomainException('Неверная ссылка подтверждения.');
        }

        if ($team->confirmation_expires_at && $team->confirmation_expires_at->isPast()) {
            throw new DomainException('Время подтверждения истекло.');
        }

        DB::transaction(function () use ($team) {
            $team->update([
                'status'                  => 'approved',
                'reserve_position'        => null,
                'confirmation_token'      => null,
                'confirmation_expires_at' => null,
                'confirmed_at'            => now(),
            ]);
        });
    }

    /**
     * Капитан отказывается или истекает время → перемещаем в конец резерва.
     */
    public function expireEventReserveOffer(EventTeam $team): void
    {
        if ($team->confirmation_token === null) {
            return; // Уже обработано
        }

        $event = $team->event;

        DB::transaction(function () use ($team, $event) {
            $newPos = $this->nextEventReservePosition($event->id, (int) $team->occurrence_id);
            $team->update([
                'confirmation_token'      => null,
                'confirmation_expires_at' => null,
                'reserve_position'        => $newPos,
            ]);
            $this->reindexEventReserve($event->id, (int) $team->occurrence_id);
        });

        // Предлагаем следующей команде
        $this->fillFromEventReserve($event->id, (int) $team->occurrence_id);
    }
}