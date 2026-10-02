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
        // Продление прибавляет срок к остатку действующей подписки, а не отсчитывается от now()
        $current = $this->getActive($user);
        $base    = $current ? $current->expires_at->copy() : now();

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
            'expires_at'     => $base->addDays($days),
            'payment_method' => $method,
            'amount_rub'     => $price > 0 ? $price : null,
        ]);
    }

    /** Цена тарифа в рублях — из настроек платформы (как на странице /organizer-pro), иначе значения по умолчанию. */
    public function priceRub(string $plan): int
    {
        $s = \App\Models\PlatformPaymentSetting::first();

        return match ($plan) {
            'month'   => (int) ($s?->organizer_pro_month_rub   ?? OrganizerSubscription::planPrice('month')),
            'quarter' => (int) ($s?->organizer_pro_quarter_rub ?? OrganizerSubscription::planPrice('quarter')),
            'half'    => (int) ($s?->organizer_pro_half_rub    ?? OrganizerSubscription::planPrice('half')),
            'year'    => (int) ($s?->organizer_pro_year_rub    ?? OrganizerSubscription::planPrice('year')),
            default   => 0,
        };
    }

    /** Ожидающая оплаты подписка пользователя (status=pending) вместе с платежом. */
    public function getPending(User $user): ?OrganizerSubscription
    {
        return OrganizerSubscription::query()
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->latest('id')
            ->first();
    }

    /**
     * Заявка на платный тариф: pending-подписка + pending-платёж. Подписка НЕ действует, пока админ не подтвердит оплату.
     * Если заявка уже отправлена на проверку («Я оплатил») — возвращаем её как есть; неоплаченные старые заявки отменяем.
     *
     * @return array{0: OrganizerSubscription, 1: \App\Models\Payment, 2: bool} [подписка, платёж, новая ли заявка]
     */
    public function createPending(User $user, string $plan): array
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($user, $plan) {
            $existing = OrganizerSubscription::query()
                ->where('user_id', $user->id)->where('status', 'pending')->lockForUpdate()->get();

            foreach ($existing as $old) {
                $oldPayment = $old->payment_id ? \App\Models\Payment::find($old->payment_id) : null;
                if ($oldPayment && $oldPayment->user_confirmed) {
                    return [$old, $oldPayment, false];
                }
            }
            foreach ($existing as $old) {
                $old->update(['status' => 'cancelled']);
                if ($old->payment_id) {
                    \App\Models\Payment::where('id', $old->payment_id)->where('status', 'pending')->update(['status' => 'cancelled']);
                }
            }

            $price    = $this->priceRub($plan);
            $settings = \App\Models\PlatformPaymentSetting::first();

            $payment = \App\Models\Payment::create([
                'user_id'      => $user->id,
                'organizer_id' => null,
                'method'       => $settings?->method ?? 'manual',
                'status'       => 'pending',
                'amount_minor' => $price * 100,
                'currency'     => 'RUB',
            ]);

            $sub = OrganizerSubscription::query()->create([
                'user_id'        => $user->id,
                'plan'           => $plan,
                'status'         => 'pending',
                'starts_at'      => now(),
                'expires_at'     => now()->addDays(OrganizerSubscription::planDays($plan)),
                'payment_method' => $settings?->method ?? 'manual',
                'payment_id'     => $payment->id,
                'amount_rub'     => $price,
            ]);

            return [$sub, $payment, true];
        });
    }

    /** Подтверждение оплаты: pending → active, срок прибавляется к остатку действующей подписки. */
    public function activatePending(OrganizerSubscription $pending): OrganizerSubscription
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($pending) {
            $user    = User::findOrFail($pending->user_id);
            $current = $this->getActive($user);
            $base    = $current ? $current->expires_at->copy() : now();

            OrganizerSubscription::query()
                ->where('user_id', $pending->user_id)
                ->where('status', 'active')
                ->update(['status' => 'cancelled']);

            $pending->update([
                'status'     => 'active',
                'starts_at'  => now(),
                'expires_at' => $base->addDays(OrganizerSubscription::planDays($pending->plan)),
            ]);

            return $pending->fresh();
        });
    }

    public function expireOld(): int
    {
        return OrganizerSubscription::query()
            ->where('status', 'active')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);
    }
}
