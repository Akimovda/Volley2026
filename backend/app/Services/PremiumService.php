<?php

namespace App\Services;

use App\Models\User;
use App\Models\PremiumSubscription;
use Carbon\Carbon;

class PremiumService
{
    public function activate(User $user, string $plan, ?int $referredBy = null): PremiumSubscription
    {
        // Деактивируем старую если есть
        PremiumSubscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->update(['status' => 'expired']);

        $days = PremiumSubscription::planDays($plan);
        $now  = Carbon::now();

        return PremiumSubscription::create([
            'user_id'     => $user->id,
            'plan'        => $plan,
            'status'      => 'active',
            'starts_at'   => $now,
            'expires_at'  => $now->copy()->addDays($days),
            'referred_by' => $referredBy,
        ]);
    }

    public function isPremium(User $user): bool
    {
        return PremiumSubscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->exists();
    }

    public function getActive(User $user): ?PremiumSubscription
    {
        return PremiumSubscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->first();
    }

    public function renew(User $user, string $plan): PremiumSubscription
    {
        $days = PremiumSubscription::planDays($plan);
        $sub  = $this->getActive($user);

        if ($sub) {
            // Продлеваем от текущей даты окончания
            $sub->expires_at = $sub->expires_at->addDays($days);
            $sub->save();
            return $sub;
        }

        // Если нет активной — создаём новую
        return $this->activate($user, $plan);
    }

    /** Запускать по расписанию: premium:expire */
    public function expireAll(): int
    {
        return PremiumSubscription::where('status', 'active')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'expired']);
    }
}
