<?php

namespace App\Traits;

use App\Models\Friendship;
use App\Models\PremiumSubscription;
use App\Models\ProfileVisit;

trait HasPremium
{
    public function isPremium(): bool
    {
        return PremiumSubscription::where('user_id', $this->id)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->exists();
    }

    public function activePremium(): ?PremiumSubscription
    {
        return PremiumSubscription::where('user_id', $this->id)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->first();
    }

    // ===== ДРУЗЬЯ =====

    public function friends()
    {
        return $this->belongsToMany(
            \App\Models\User::class,
            'friendships',
            'user_id',
            'friend_id'
        )->withTimestamps();
    }

    public function isFriendWith(int $userId): bool
    {
        return Friendship::where('user_id', $this->id)
            ->where('friend_id', $userId)
            ->exists();
    }

    // ===== ГОСТИ ПРОФИЛЯ =====

    public function recordVisit(int $visitorId): void
    {
        if ($visitorId === $this->id) return;

        // Обновляем или создаём запись визита
        ProfileVisit::updateOrCreate(
            [
                'profile_user_id' => $this->id,
                'visitor_user_id' => $visitorId,
            ],
            ['visited_at' => now()]
        );
    }

    public function recentVisitors(int $days = 7)
    {
        return ProfileVisit::where('profile_user_id', $this->id)
            ->where('visited_at', '>=', now()->subDays($days))
            ->with('visitor')
            ->orderByDesc('visited_at')
            ->get();
    }
}
