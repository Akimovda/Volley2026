<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OrganizerSubscription;
use App\Models\User;

class OrganizerSubscriptionService
{
    public function getActive(User $user): ?OrganizerSubscription
    {
        return OrganizerSubscription::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->latest('expires_at')
            ->first();
    }

    public function hasActive(User $user): bool
    {
        return $this->getActive($user) !== null;
    }

    public function activate(User $user, string $plan, string $method = 'manual'): OrganizerSubscription
    {
        // Деактивируем предыдущие
        OrganizerSubscription::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->update(['status' => 'cancelled']);

        $days  = OrganizerSubscription::planDays($plan);
        $price = OrganizerSubscription::planPrice($plan);

        return OrganizerSubscription::query()->create([
            'user_id'        => $user->id,
            'plan'           => $plan,
            'status'         => 'active',
            'starts_at'      => now(),
            'expires_at'     => now()->addDays($days),
            'payment_method' => $method,
            'amount_rub'     => $price > 0 ? $price : null,
        ]);
    }

    public function expireOld(): int
    {
        return OrganizerSubscription::query()
            ->where('status', 'active')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);
    }
}
