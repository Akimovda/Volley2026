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
     * Срок подписки считается ОТ ДАТЫ ПЛАТЕЖА (payment->created_at), не от даты подтверждения
     * (а при действующей подписке — от её даты окончания, см. ниже) —
     * иначе платёж, зависший месяцами (см. апрельские pending), после подтверждения дал бы
     * игроку полный новый срок вместо оставшегося.
     */
    public function confirmPending(\App\Models\Payment $payment): PremiumSubscription
    {
        $sub = PremiumSubscription::where('payment_id', $payment->id)
            ->where('status', 'pending')
            ->firstOrFail();

        $days = PremiumSubscription::planDays($sub->plan);

        // Продление при ещё действующей подписке прибавляет срок к её дате окончания (остаток не теряется);
        // иначе срок считается от даты платежа (см. докблок выше).
        $current = PremiumSubscription::where('user_id', $sub->user_id)
            ->where('id', '!=', $sub->id)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->orderByDesc('expires_at')
            ->first();

        $startsAt  = $current ? $current->expires_at->copy() : $payment->created_at->copy();
        $expiresAt = $startsAt->copy()->addDays($days);

        // Платёж мог зависнуть в pending месяцами (апрельские кейсы) — если срок, посчитанный
        // от даты платежа, уже истёк, подтверждение НЕ должно давать реальный доступ и тем более
        // не должно гасить чью-то ДЕЙСТВИТЕЛЬНО активную подписку той же дедуп-логикой ниже.
        $isCurrentlyValid = $expiresAt->isFuture();

        if ($isCurrentlyValid) {
            PremiumSubscription::where('user_id', $sub->user_id)
                ->where('id', '!=', $sub->id)
                ->where('status', 'active')
                ->update(['status' => 'expired']);
        }

        $sub->update([
            'status'     => $isCurrentlyValid ? 'active' : 'expired',
            'starts_at'  => $startsAt,
            'expires_at' => $expiresAt,
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
        $expiredSubs = PremiumSubscription::where('status', 'active')
            ->where('expires_at', '<=', now())
            ->with('user')
            ->get();

        // Пользователи, у которых нет другой ещё действующей подписки (после продления старая уже expired,
        // но на всякий случай не снимаем фичи у того, кто по факту остаётся Premium)
        $expiredUserIds = $expiredSubs->pluck('user_id')->unique()
            ->reject(fn ($uid) => PremiumSubscription::where('user_id', $uid)
                ->where('status', 'active')->where('expires_at', '>', now())->exists())
            ->values();

        // Сколько подписок/автозаписей будет снято — считаем ДО удаления (для текста уведомления)
        $followCounts = $expiredUserIds->isEmpty() ? collect()
            : \App\Models\PlayerFollow::whereIn('follower_user_id', $expiredUserIds)
                ->selectRaw('follower_user_id, count(*) c')->groupBy('follower_user_id')->pluck('c', 'follower_user_id');
        $autoCounts = $expiredUserIds->isEmpty() ? collect()
            : \App\Models\PremiumAutoBooking::whereIn('user_id', $expiredUserIds)
                ->selectRaw('user_id, count(*) c')->groupBy('user_id')->pluck('c', 'user_id');

        $count = PremiumSubscription::where('status', 'active')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'expired', 'expiry_notice_days' => 0]);

        // Удаляем подписки на игроков и джобы авто-записи — фичи только для активного премиума
        if ($expiredUserIds->isNotEmpty()) {
            \App\Models\PlayerFollow::whereIn('follower_user_id', $expiredUserIds)->delete();
            \App\Models\PremiumAutoBooking::whereIn('user_id', $expiredUserIds)->delete();
        }

        // Уведомление «Premium закончился» — только о недавно закончившихся (давние, например при первом
        // запуске после деплоя, закрываются молча), по одному на пользователя
        $notified = [];
        foreach ($expiredSubs as $sub) {
            if (!$sub->user || isset($notified[$sub->user_id]) || !$expiredUserIds->contains($sub->user_id)) {
                continue;
            }
            if ($sub->expires_at->timestamp < now()->timestamp - 3 * 86400) {
                continue;
            }
            $notified[$sub->user_id] = true;
            try {
                app(UserNotificationService::class)->createPremiumExpiredNotification(
                    $sub->user, $sub,
                    (int) ($followCounts[$sub->user_id] ?? 0),
                    (int) ($autoCounts[$sub->user_id] ?? 0)
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('premium.expired_notice_failed', ['subscription_id' => $sub->id, 'error' => $e->getMessage()]);
            }
        }

        return $count;
    }
}
