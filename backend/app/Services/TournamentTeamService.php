<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventTeam;
use App\Models\EventTeamApplication;
use App\Models\EventTeamMember;
use App\Models\EventTeamMemberAudit;
use App\Models\EventTournamentSetting;
use App\Models\User;
use DomainException;
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
        ?string $captainPositionCode = null
    ): EventTeam {
        return DB::transaction(function () use ($event, $captain, $name, $occurrenceId, $teamKind, $captainPositionCode) {
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

            $team = EventTeam::query()->create([
                'event_id' => $event->id,
                'occurrence_id' => $occurrenceId,
                'captain_user_id' => $captain->id,
                'name' => trim($name),
                'team_kind' => $teamKind,
                'status' => 'draft',
                'invite_code' => $this->generateInviteCode(),
                'is_complete' => false,
                'last_checked_at' => now(),
                'confirmed_at' => null,
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

            return $this->refreshTeamState($team->fresh());
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
     * Получить доступные позиции (амплуа) для команды
     */
    public function getAvailablePositionOptions(EventTeam $team): array
    {
        if ((string) $team->team_kind !== 'classic_team') {
            return [];
        }
    
        $settings = $this->getSettings($team->event);
        $scheme = $settings?->getGameScheme() ?? '5x1';
    
        $positions = (array) config("volleyball.classic.{$scheme}.positions", []);
        $result = [];
    
        foreach ($positions as $code => $count) {
            $result[$code] = $this->positionLabel($code) . " (макс. {$count})";
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
     * Пригласить или добавить участника в команду
     */
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
    
    public function submitApplication(EventTeam $team, User $submittedBy): EventTeamApplication
    {
        return DB::transaction(function () use ($team, $submittedBy) {
            // НЕ вызываем refreshTeamState() здесь — он уже вызван в refreshTeamState->submitApplication
            // Иначе бесконечная рекурсия: refreshTeamState → submitApplication → refreshTeamState → ...
            $team = $team->fresh(['members.user', 'event.tournamentSetting']);

            if (!$team->is_complete || $team->status !== 'ready') {
                throw new DomainException('Команда ещё не готова к подаче заявки.');
            }

            // === Проверка уровня всех членов команды ===
            $this->validateTeamMembersLevel($team);

            // === Проверка гендерных ограничений ===
            $this->validateTeamGender($team);

            $existing = EventTeamApplication::query()
                ->where('event_team_id', $team->id)
                ->first();

            if ($existing) {
                throw new DomainException('Заявка уже существует.');
            }

            // Определяем режим одобрения
            $settings = \App\Models\EventTournamentSetting::where('event_id', $team->event_id)->first();
            $isAutoApproval = ($settings->application_mode ?? 'manual') === 'auto';

            $applicationStatus = $isAutoApproval ? 'approved' : 'pending';
            $teamStatus = $isAutoApproval ? 'submitted' : 'pending';

            $application = EventTeamApplication::query()->create([
                'event_id' => $team->event_id,
                'event_team_id' => $team->id,
                'status' => $applicationStatus,
                'submitted_by_user_id' => $submittedBy->id,
                'applied_at' => now(),
                'reviewed_by_user_id' => $isAutoApproval ? null : null,
                'reviewed_at' => $isAutoApproval ? now() : null,
                'rejection_reason' => null,
                'decision_comment' => $isAutoApproval ? 'Автоматическое одобрение' : null,
                'meta' => null,
            ]);

            $team->update([
                'status' => $teamStatus,
            ]);

            $this->audit(
                team: $team,
                action: 'application_submitted',
                performedByUserId: $submittedBy->id,
                newValue: [
                    'application_id' => $application->id,
                    'status' => $applicationStatus,
                ],
            );

            return $application;
        });

        // Уведомляем организатора о новой заявке (после коммита транзакции)
        if ($application->status === 'pending') {
            try {
                $event = $team->event;
                $organizerId = (int) $event->organizer_id;
                $setupUrl = route('tournament.setup', $event);

                app(\App\Services\UserNotificationService::class)->create(
                    userId: $organizerId,
                    type: 'tournament_application_received',
                    title: 'Новая заявка на турнир',
                    body: "Команда «{$team->name}» подала заявку на турнир «{$event->title}». Одобрите или отклоните заявку.",
                    payload: [
                        'event_id' => $event->id,
                        'team_id' => $team->id,
                        'application_id' => $application->id,
                        'team_name' => $team->name,
                        'event_title' => $event->title,
                        'button_text' => 'Управление турниром',
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

        if (
            $settings?->auto_submit_when_ready &&
            $team->fresh()->status === 'ready' &&
            !$team->application()->exists()
        ) {
            $this->submitApplication($team->fresh(), $team->captain);
        }

        return $team->fresh(['members.user', 'application', 'event.tournamentSetting']);
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

        // Не должен быть в другой активной команде на это мероприятие
        $alreadyInTeam = EventTeamMember::whereHas('team', function ($q) use ($team) {
            $q->where('event_id', $team->event_id)
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
    public function disbandTeam(EventTeam $team, int $captainUserId): void
    {
        if ((int) $team->captain_user_id !== $captainUserId) {
            throw new DomainException('Только капитан может расформировать команду.');
        }

        $event = $team->event;
        $members = $team->members()->with('user')->get();
        $teamName = $team->name;

        // Рефанд
        $this->refundForTeam($team, $event);

        // Удаляем заявку
        \App\Models\EventTeamApplication::where('event_team_id', $team->id)->delete();
        // Удаляем приглашения
        \App\Models\EventTeamInvite::where('event_team_id', $team->id)->delete();
        // Удаляем членов
        $team->members()->delete();
        // Удаляем команду
        $team->delete();

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

}