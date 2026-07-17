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

    /**
     * Подтверждение админом pending-платежа Premium.
     * Срок подписки считается ОТ ДАТЫ ПЛАТЕЖА (payment->created_at), не от даты подтверждения —
     * иначе платёж, зависший месяцами (см. апрельские pending), после подтверждения дал бы
     * игроку полный новый срок вместо оставшегося.
     */
    public function confirmPending(\App\Models\Payment $payment): PremiumSubscription
    {
        $sub = PremiumSubscription::where('payment_id', $payment->id)
            ->where('status', 'pending')
            ->firstOrFail();

        // Деактивируем прежний активный (если есть) — та же логика, что в activate()
        PremiumSubscription::where('user_id', $sub->user_id)
            ->where('id', '!=', $sub->id)
            ->where('status', 'active')
            ->update(['status' => 'expired']);

        $startsAt = $payment->created_at->copy();
        $days     = PremiumSubscription::planDays($sub->plan);

        $sub->update([
            'status'     => 'active',
            'starts_at'  => $startsAt,
            'expires_at' => $startsAt->copy()->addDays($days),
        ]);

        return $sub->fresh();
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
        $expiredUserIds = PremiumSubscription::where('status', 'active')
            ->where('expires_at', '<=', now())
            ->pluck('user_id');

        $count = PremiumSubscription::where('status', 'active')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'expired']);

        // Удаляем подписки на игроков — фича только для активного премиума
        if ($expiredUserIds->isNotEmpty()) {
            \App\Models\PlayerFollow::whereIn('follower_user_id', $expiredUserIds)->delete();
        }

        return $count;
    }
}
