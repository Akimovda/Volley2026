<?php

namespace App\Services;

use App\Models\EventTeam;
use App\Models\EventTeamInvite;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\UserNotificationService;

final class TournamentTeamInviteService
{
    public function __construct(
        private TournamentTeamService $teamService,
        private UserNotificationService $userNotificationService
    ) {}

    public function createInvite(
        EventTeam $team,
        int $invitedUserId,
        int $invitedByUserId,
        string $teamRole = 'player',
        ?string $positionCode = null
    ): EventTeamInvite {
        $invite = DB::transaction(function () use ($team, $invitedUserId, $invitedByUserId, $teamRole, $positionCode) {
            if ((int) $team->captain_user_id === $invitedUserId) {
                throw new DomainException('Капитану не нужно отправлять приглашение самому себе.');
            }

            $alreadyInTeam = $team->members()
                ->where('user_id', $invitedUserId)
                ->exists();

            if ($alreadyInTeam) {
                throw new DomainException('Пользователь уже состоит в команде.');
            }

            if ((string) $team->team_kind === 'beach_pair') {
                $positionCode = null;
            }

            if ((string) $team->team_kind === 'classic_team' && $teamRole === 'player' && empty($positionCode)) {
                throw new DomainException('Для основного состава нужно указать амплуа.');
            }

            if ((string) $team->team_kind === 'classic_team' && !empty($positionCode)) {
                $allowedPositions = array_keys($this->teamService->getAvailablePositionOptions($team));

                if (!in_array($positionCode, $allowedPositions, true)) {
                    throw new DomainException('Недопустимое амплуа для этой схемы игры.');
                }
            }
            $existingPendingInvite = EventTeamInvite::query()
                ->where('event_team_id', $team->id)
                ->where('invited_user_id', $invitedUserId)
                ->where('status', 'pending')
                ->first();
            
            if ($existingPendingInvite) {
                throw new DomainException('У этого игрока уже есть активное приглашение в команду.');
            }

            $invite = EventTeamInvite::query()->create([
                'event_id' => $team->event_id,
                'event_team_id' => $team->id,
                'invited_user_id' => $invitedUserId,
                'invited_by_user_id' => $invitedByUserId,
                'team_role' => $teamRole,
                'position_code' => $positionCode,
                'token' => Str::uuid()->toString(),
                'status' => 'pending',
                'expires_at' => now()->addDays(7),
                'meta' => null,
            ]);
            
            return $invite;
        });

        // Уведомления отправляем ПОСЛЕ коммита транзакции
        try {
            $this->sendInviteNotifications($invite);
        } catch (\Throwable $e) {
            report($e);
        }

        return $invite;
    }

    public function getByTokenOrFail(string $token): EventTeamInvite
    {
        $invite = EventTeamInvite::query()
            ->with([
                'event.location.city',
                'event.tournamentSetting',
                'team.captain',
                'team.members.user',
                'invitedUser',
                'invitedByUser',
            ])
            ->where('token', $token)
            ->first();

        if (!$invite) {
            throw new DomainException('Приглашение не найдено.');
        }

        if (!$invite->isUsable()) {
            throw new DomainException('Приглашение уже недействительно.');
        }

        return $invite;
    }

    public function acceptInvite(string $token, int $userId): EventTeam
    {
        return DB::transaction(function () use ($token, $userId) {
            $invite = EventTeamInvite::query()
                ->with(['team.event'])
                ->where('token', $token)
                ->lockForUpdate()
                ->first();

            if (!$invite) {
                throw new DomainException('Приглашение не найдено.');
            }

            if ((int) $invite->invited_user_id !== $userId) {
                throw new DomainException('Это приглашение адресовано другому пользователю.');
            }

            if (!$invite->isUsable()) {
                throw new DomainException('Приглашение уже недействительно.');
            }

            $user = User::query()->findOrFail($userId);

            $this->teamService->inviteOrJoinMember(
                team: $invite->team,
                user: $user,
                invitedByUserId: $invite->invited_by_user_id,
                teamRole: $invite->team_role,
                positionCode: $invite->position_code,
                autoConfirm: true,
            );

            $invite->update([
                'status' => 'accepted',
                'accepted_at' => now(),
            ]);

            EventTeamInvite::query()
                ->where('event_id', $invite->event_id)
                ->where('invited_user_id', $invite->invited_user_id)
                ->where('id', '!=', $invite->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'revoked',
                    'revoked_at' => now(),
                ]);

            return $invite->team->fresh(['members.user', 'application', 'event.tournamentSetting']);
        });
    }

    public function revokeInvite(int $inviteId, int $byUserId): void
    {
        DB::transaction(function () use ($inviteId, $byUserId) {
            $invite = EventTeamInvite::query()
                ->with('team')
                ->where('id', $inviteId)
                ->lockForUpdate()
                ->first();

            if (!$invite) {
                throw new DomainException(__('events.tinv_err_not_found'));
            }

            $isCaptain = (int) $invite->team->captain_user_id === $byUserId;
            $isInviter = (int) $invite->invited_by_user_id === $byUserId;

            if (!$isCaptain && !$isInviter) {
                throw new DomainException(__('events.tinv_err_only_captain'));
            }

            if ($invite->status !== 'pending') {
                throw new DomainException(__('events.tinv_err_only_pending'));
            }

            $invite->update([
                'status' => 'revoked',
                'revoked_at' => now(),
            ]);
        });
    }

    public function declineInvite(string $token, int $userId): void
    {
        DB::transaction(function () use ($token, $userId) {
            $invite = EventTeamInvite::query()
                ->where('token', $token)
                ->lockForUpdate()
                ->first();

            if (!$invite) {
                throw new DomainException('Приглашение не найдено.');
            }

            if ((int) $invite->invited_user_id !== $userId) {
                throw new DomainException('Это приглашение адресовано другому пользователю.');
            }

            if (!$invite->isUsable()) {
                throw new DomainException('Приглашение уже недействительно.');
            }

            $invite->update([
                'status' => 'declined',
                'declined_at' => now(),
            ]);
        });
    }
    private function sendInviteNotifications(EventTeamInvite $invite): void
    {
        $invite->loadMissing([
            'event',
            'team',
            'invitedUser',
            'invitedByUser',
        ]);
    
        $inviteUrl = route('tournamentTeamInvites.show', ['token' => $invite->token]);
    
        $notification = $this->userNotificationService->createTournamentTeamInviteNotification(
            toUserId: (int) $invite->invited_user_id,
            fromUserId: (int) $invite->invited_by_user_id,
            eventId: (int) $invite->event_id,
            teamId: (int) $invite->event_team_id,
            inviteId: (int) $invite->id,
            teamName: (string) ($invite->team->name ?? 'Команда'),
            eventTitle: (string) ($invite->event->title ?? 'Турнир'),
            inviteUrl: $inviteUrl,
            teamRole: (string) $invite->team_role,
            positionCode: $invite->position_code
        );
    
        $channels = ['in_app', 'telegram', 'vk', 'max'];
    
        $meta = $invite->meta ?? [];
        $meta['sent_channels'] = $channels;
        $meta['invite_url'] = $inviteUrl;
        $meta['notification_id'] = $notification->id;
        $meta['notified_at'] = now()->toDateTimeString();
    
        $invite->update([
            'meta' => $meta,
        ]);
    }
}