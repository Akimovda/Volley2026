<?php

namespace App\Services;

use App\Models\Event;
use App\Models\User;
use App\Services\UserNotificationService;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;


final class EventRegistrationGroupService
{
    public function __construct(
        private UserNotificationService $userNotificationService
    ) {}
    
    public function createGroupForRegistration(int $eventId, int $userId): string
    {
        return DB::transaction(function () use ($eventId, $userId) {
            $event = $this->getEvent($eventId);
            $this->assertBeachMixedGroupEvent($event);

            $registration = $this->getActiveRegistration($eventId, $userId);

            if (!empty($registration->group_key)) {
                return (string) $registration->group_key;
            }

            $groupKey = $this->generateGroupKey($eventId);

            DB::table('event_registrations')
                ->where('id', $registration->id)
                ->update([
                    'group_key' => $groupKey,
                    'updated_at' => now(),
                ]);

            return $groupKey;
        });
    }

    public function inviteToGroup(int $eventId, int $fromUserId, int $toUserId): array
        {
            return DB::transaction(function () use ($eventId, $fromUserId, $toUserId) {
                if ($fromUserId === $toUserId) {
                    throw new DomainException('Нельзя пригласить самого себя.');
                }
        
                $toUser = User::query()->find($toUserId);
                if (!$toUser) {
                    throw new DomainException('Приглашаемый пользователь не найден.');
                }
        
                $event = $this->getEvent($eventId);
                $this->assertBeachMixedGroupEvent($event);
        
                $groupSize = $this->resolveGroupSize($event);
        
                $fromRegistration = $this->getActiveRegistration($eventId, $fromUserId);
        
                // пытаемся найти регистрацию, но НЕ падаем если её нет
                $toRegistration = DB::table('event_registrations')
                    ->where('event_id', $eventId)
                    ->where('user_id', $toUserId)
                    ->where(function ($q) {
                        $q->whereNull('is_cancelled')->orWhere('is_cancelled', false);
                    })
                    ->where(function ($q) {
                        $q->whereNull('status')->orWhere('status', '<>', 'cancelled');
                    })
                    ->first();
        
                if ($toRegistration && !empty($toRegistration->group_key)) {
                    throw new DomainException('Игрок уже состоит в группе.');
                }
        
                $this->assertNoPendingInviteForUser($eventId, $toUserId);
        
                $groupKey = !empty($fromRegistration->group_key)
                    ? (string) $fromRegistration->group_key
                    : $this->createGroupForRegistration($eventId, $fromUserId);
        
                $membersCount = $this->countGroupMembers($eventId, $groupKey);
        
                if ($membersCount >= $groupSize) {
                    throw new DomainException('Группа уже полностью укомплектована.');
                }
        
                $existingInvite = DB::table('event_registration_group_invites')
                    ->where('event_id', $eventId)
                    ->where('group_key', $groupKey)
                    ->where('to_user_id', $toUserId)
                    ->first();
        
                $autoJoin = $toRegistration ? false : true;
                $inviteId = null;
        
                if ($existingInvite) {
                    if ($existingInvite->status === 'pending') {
                        throw new DomainException('Приглашение этому игроку уже отправлено.');
                    }
        
                    DB::table('event_registration_group_invites')
                        ->where('id', $existingInvite->id)
                        ->update([
                            'from_user_id' => $fromUserId,
                            'status' => 'pending',
                            'auto_join_after_registration' => $autoJoin,
                            'updated_at' => now(),
                        ]);
        
                    $inviteId = (int) $existingInvite->id;
                } else {
                    $inviteId = (int) DB::table('event_registration_group_invites')->insertGetId([
                        'event_id' => $eventId,
                        'group_key' => $groupKey,
                        'from_user_id' => $fromUserId,
                        'to_user_id' => $toUserId,
                        'status' => 'pending',
                        'auto_join_after_registration' => $autoJoin,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
        
                $this->userNotificationService->createGroupInviteNotification(
                    toUserId: $toUserId,
                    fromUserId: $fromUserId,
                    eventId: $eventId,
                    inviteId: $inviteId,
                    groupKey: $groupKey,
                    autoJoinAfterRegistration: $autoJoin
                );
        
                return [
                    'group_key' => $groupKey,
                    'group_size' => $groupSize,
                    'members_count' => $membersCount,
                    'invited_user_id' => $toUserId,
                    'invite_id' => $inviteId,
                ];
            });
        }

    public function acceptInvite(int $inviteId, int $userId): array
    {
        return DB::transaction(function () use ($inviteId, $userId) {
            $invite = DB::table('event_registration_group_invites')
                ->where('id', $inviteId)
                ->lockForUpdate()
                ->first();

            if (!$invite) {
                throw new DomainException('Приглашение не найдено.');
            }

            if ((int) $invite->to_user_id !== $userId) {
                throw new DomainException('Нельзя принять чужое приглашение.');
            }

            if ($invite->status !== 'pending') {
                throw new DomainException('Это приглашение уже неактуально.');
            }

            $event = $this->getEvent((int) $invite->event_id);
            $this->assertBeachMixedGroupEvent($event);

            $registration = DB::table('event_registrations')
                ->where('event_id', $invite->event_id)
                ->where('user_id', $userId)
                ->where(function ($q) {
                    $q->whereNull('is_cancelled')->orWhere('is_cancelled', false);
                })
                ->where(function ($q) {
                    $q->whereNull('status')->orWhere('status', '<>', 'cancelled');
                })
                ->first();
            
            if (!$registration) {
                throw new DomainException(
                    'Сначала необходимо записаться на мероприятие, чтобы принять приглашение.'
                );
            }

            if (!empty($registration->group_key) && $registration->group_key !== $invite->group_key) {
                throw new DomainException('Игрок уже состоит в другой группе.');
            }

            $groupSize = $this->resolveGroupSize($event);
            $membersCount = $this->countGroupMembers((int) $invite->event_id, (string) $invite->group_key);

            if ($membersCount >= $groupSize) {
                throw new DomainException('Группа уже полностью укомплектована.');
            }

            DB::table('event_registrations')
                ->where('id', $registration->id)
                ->update([
                    'group_key' => $invite->group_key,
                    'updated_at' => now(),
                ]);

            DB::table('event_registration_group_invites')
                ->where('id', $invite->id)
                ->update([
                    'status' => 'accepted',
                    'updated_at' => now(),
                ]);

            DB::table('event_registration_group_invites')
                ->where('event_id', $invite->event_id)
                ->where('to_user_id', $userId)
                ->where('status', 'pending')
                ->where('id', '<>', $invite->id)
                ->update([
                    'status' => 'cancelled',
                    'updated_at' => now(),
                ]);
                
                $this->userNotificationService->markGroupInviteNotificationsAsRead(
                userId: $userId,
                inviteId: (int) $invite->id
            );

            return [
                'group_key' => (string) $invite->group_key,
                'group_size' => $groupSize,
                'members_count' => $this->countGroupMembers((int) $invite->event_id, (string) $invite->group_key),
                'is_full' => $this->countGroupMembers((int) $invite->event_id, (string) $invite->group_key) >= $groupSize,
            ];
        });
    }

    public function declineInvite(int $inviteId, int $userId): void
    {
        DB::transaction(function () use ($inviteId, $userId) {
            $invite = DB::table('event_registration_group_invites')
                ->where('id', $inviteId)
                ->lockForUpdate()
                ->first();

            if (!$invite) {
                throw new DomainException('Приглашение не найдено.');
            }

            if ((int) $invite->to_user_id !== $userId) {
                throw new DomainException('Нельзя отклонить чужое приглашение.');
            }

            if ($invite->status !== 'pending') {
                throw new DomainException('Это приглашение уже неактуально.');
            }

            DB::table('event_registration_group_invites')
                ->where('id', $invite->id)
                ->update([
                    'status' => 'declined',
                    'updated_at' => now(),
                ]);
                $this->userNotificationService->markGroupInviteNotificationsAsRead(
                userId: $userId,
                inviteId: (int) $invite->id
            );
        });
    }

    public function leaveGroup(int $eventId, int $userId): void
    {
        DB::transaction(function () use ($eventId, $userId) {
            $registration = $this->getActiveRegistration($eventId, $userId);

            if (empty($registration->group_key)) {
                throw new DomainException('Игрок не состоит в группе.');
            }

            $groupKey = (string) $registration->group_key;

            DB::table('event_registrations')
                ->where('id', $registration->id)
                ->update([
                    'group_key' => null,
                    'updated_at' => now(),
                ]);

            DB::table('event_registration_group_invites')
                ->where('event_id', $eventId)
                ->where('group_key', $groupKey)
                ->where(function ($q) use ($userId) {
                    $q->where('from_user_id', $userId)
                      ->orWhere('to_user_id', $userId);
                })
                ->where('status', 'pending')
                ->update([
                    'status' => 'cancelled',
                    'updated_at' => now(),
                ]);

            $remaining = $this->countGroupMembers($eventId, $groupKey);

            if ($remaining <= 1) {
                DB::table('event_registrations')
                    ->where('event_id', $eventId)
                    ->where('group_key', $groupKey)
                    ->update([
                        'group_key' => null,
                        'updated_at' => now(),
                    ]);

                DB::table('event_registration_group_invites')
                    ->where('event_id', $eventId)
                    ->where('group_key', $groupKey)
                    ->where('status', 'pending')
                    ->update([
                        'status' => 'cancelled',
                        'updated_at' => now(),
                    ]);
            }
        });
    }

    public function listPendingInvitesForUser(int $eventId, int $userId)
    {
        return DB::table('event_registration_group_invites as i')
            ->join('users as u', 'u.id', '=', 'i.from_user_id')
            ->where('i.event_id', $eventId)
            ->where('i.to_user_id', $userId)
            ->where('i.status', 'pending')
            ->orderByDesc('i.id')
            ->get([
                'i.id',
                'i.group_key',
                'i.from_user_id',
                'i.to_user_id',
                'i.status',
                'i.auto_join_after_registration',
                'i.created_at',
                'u.name as from_user_name',
                'u.email as from_user_email',
            ]);
    }

    public function getGroupMembers(int $eventId, string $groupKey)
    {
        return DB::table('event_registrations as r')
            ->join('users as u', 'u.id', '=', 'r.user_id')
            ->where('r.event_id', $eventId)
            ->where('r.group_key', $groupKey)
            ->where(function ($q) {
                $q->whereNull('r.is_cancelled')->orWhere('r.is_cancelled', false);
            })
            ->where(function ($q) {
                $q->whereNull('r.status')->orWhere('r.status', '<>', 'cancelled');
            })
            ->orderBy('r.id')
            ->get([
                'r.id',
                'r.event_id',
                'r.user_id',
                'r.group_key',
                'u.name',
                'u.email',
            ]);
    }

    private function getEvent(int $eventId): Event
    {
        $event = Event::query()
            ->with('gameSettings')
            ->find($eventId);

        if (!$event) {
            throw new DomainException('Событие не найдено.');
        }

        return $event;
    }

    private function assertBeachMixedGroupEvent(Event $event): void
    {
        if ((string) $event->direction !== 'beach') {
            throw new DomainException('Группы доступны только для пляжного волейбола.');
        }

        if ((string) ($event->registration_mode ?? 'single') !== 'mixed_group') {
            throw new DomainException('Для этого события режим групповой записи не включён.');
        }
    }

    private function getActiveRegistration(int $eventId, int $userId): object
    {
        $registration = DB::table('event_registrations')
            ->where('event_id', $eventId)
            ->where('user_id', $userId)
            ->where(function ($q) {
                $q->whereNull('is_cancelled')->orWhere('is_cancelled', false);
            })
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', '<>', 'cancelled');
            })
            ->orderByDesc('id')
            ->first();

        if (!$registration) {
            throw new DomainException('Игрок не зарегистрирован на это событие.');
        }

        return $registration;
    }

    private function resolveGroupSize(Event $event): int
    {
        $subtype = (string) ($event->gameSettings->subtype ?? '');

        $size = EventRegistrationRules::groupSize(
            (string) $event->direction,
            $subtype
        );

        if ($size < 2) {
            throw new DomainException('Для этого подтипа игры группировка недоступна.');
        }

        return $size;
    }

    private function countGroupMembers(int $eventId, string $groupKey): int
    {
        return DB::table('event_registrations')
            ->where('event_id', $eventId)
            ->where('group_key', $groupKey)
            ->where(function ($q) {
                $q->whereNull('is_cancelled')->orWhere('is_cancelled', false);
            })
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', '<>', 'cancelled');
            })
            ->count();
    }

    private function assertNoPendingInviteForUser(int $eventId, int $userId): void
    {
        $exists = DB::table('event_registration_group_invites')
            ->where('event_id', $eventId)
            ->where('to_user_id', $userId)
            ->where('status', 'pending')
            ->exists();

        if ($exists) {
            throw new DomainException('У игрока уже есть активное приглашение в группу.');
        }
    }

    private function generateGroupKey(int $eventId): string
    {
        do {
            $groupKey = 'grp_' . $eventId . '_' . Str::lower(Str::random(12));
        } while (
            DB::table('event_registrations')
                ->where('event_id', $eventId)
                ->where('group_key', $groupKey)
                ->exists()
        );

        return $groupKey;
    }
}
