<?php

namespace App\Services;

use App\Models\EventOccurrence;
use App\Models\PlayerFollow;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class PlayerFollowService
{
    public function __construct(
        private readonly UserNotificationService $notificationService,
        private readonly PremiumService $premiumService,
    ) {}

    public function follow(User $follower, User $target): bool
    {
        if (!$this->premiumService->isPremium($follower)) {
            return false;
        }
        if (!$follower->isFriendWith($target->id)) {
            return false;
        }

        PlayerFollow::firstOrCreate([
            'follower_user_id' => $follower->id,
            'followed_user_id' => $target->id,
        ]);

        return true;
    }

    public function unfollow(User $follower, User $target): void
    {
        PlayerFollow::where('follower_user_id', $follower->id)
            ->where('followed_user_id', $target->id)
            ->delete();
    }

    public function isFollowing(User $follower, User $target): bool
    {
        return PlayerFollow::where('follower_user_id', $follower->id)
            ->where('followed_user_id', $target->id)
            ->exists();
    }

    /**
     * Вызывается после записи пользователя на мероприятие.
     * Уведомляет всех активных премиум-подписчиков.
     */
    public function notifyFollowers(User $registeredUser, EventOccurrence $occurrence): void
    {
        try {
            // Проверяем: скрыл ли пользователь свои записи от подписчиков
            $sub = $this->premiumService->getActive($registeredUser);
            if ($sub && $sub->hide_from_followers) {
                return;
            }

            $event       = $occurrence->event;
            $eventTitle  = (string) ($event->title ?? ('#' . $occurrence->event_id));
            $isPrivate   = (bool) ($event->is_private ?? false);
            $eventId     = (int) $occurrence->event_id;
            $occurrenceId = (int) $occurrence->id;

            // Получаем всех подписчиков с активным премиумом
            $followerIds = PlayerFollow::where('followed_user_id', $registeredUser->id)
                ->pluck('follower_user_id');

            foreach ($followerIds as $followerId) {
                $follower = User::find($followerId);
                if (!$follower || !$this->premiumService->isPremium($follower)) {
                    continue;
                }

                $this->notificationService->createFollowedPlayerRegisteredNotification(
                    userId:       (int) $followerId,
                    followedId:   (int) $registeredUser->id,
                    followedName: $registeredUser->name,
                    eventId:      $eventId,
                    occurrenceId: $occurrenceId,
                    eventTitle:   $eventTitle,
                    isPrivate:    $isPrivate,
                );
            }
        } catch (\Throwable $e) {
            Log::warning('PlayerFollowService::notifyFollowers error: ' . $e->getMessage());
        }
    }

    /**
     * Удалить все подписки пользователя (при истечении премиума).
     */
    public function clearFollowsForUser(int $userId): void
    {
        PlayerFollow::where('follower_user_id', $userId)->delete();
    }
}
