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
                newValue: [
                    'confirmation_status' => 'declined',
                ],
            );

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
            $team = $this->refreshTeamState($team->fresh());

            if (!$team->is_complete || $team->status !== 'ready') {
                throw new DomainException('Команда ещё не готова к подаче заявки.');
            }

            $existing = EventTeamApplication::query()
                ->where('event_team_id', $team->id)
                ->first();

            if ($existing) {
                throw new DomainException('Заявка уже существует.');
            }

            $application = EventTeamApplication::query()->create([
                'event_id' => $team->event_id,
                'event_team_id' => $team->id,
                'status' => 'pending',
                'submitted_by_user_id' => $submittedBy->id,
                'applied_at' => now(),
                'reviewed_by_user_id' => null,
                'reviewed_at' => null,
                'rejection_reason' => null,
                'decision_comment' => null,
                'meta' => null,
            ]);

            $team->update([
                'status' => 'pending',
            ]);

            $this->audit(
                team: $team,
                action: 'application_submitted',
                performedByUserId: $submittedBy->id,
                newValue: [
                    'application_id' => $application->id,
                    'status' => 'pending',
                ],
            );

            return $application;
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
}