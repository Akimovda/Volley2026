<?php

namespace App\Services;

use App\Models\OrganizerSubscription;
use App\Models\Payment;
use App\Models\PremiumSubscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Зачисление оплаченного через ЮKassa платежа платформы (Организатор Pro / Premium):
 * активирует pending-подписку. Идемпотентно — вызывается и из вебхука, и из возврата пользователя.
 */
class PlatformPaymentService
{
    public function __construct(
        private OrganizerSubscriptionService $pro,
        private PremiumService $premium,
        private UserNotificationService $notifications,
    ) {}

    /** 'org_pro' | 'premium' | null — к какой подписке привязан платёж. */
    public function typeOf(Payment $payment): ?string
    {
        if (OrganizerSubscription::where('payment_id', $payment->id)->exists()) {
            return 'org_pro';
        }
        if (PremiumSubscription::where('payment_id', $payment->id)->exists()) {
            return 'premium';
        }
        return null;
    }

    /** @return bool true — платёж зачтён (сейчас или раньше). */
    public function settle(Payment $payment): bool
    {
        $activated = null;

        DB::transaction(function () use ($payment, &$activated) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->first();
            if (!$locked || $locked->status !== 'pending') {
                return;
            }

            $pro = OrganizerSubscription::where('payment_id', $locked->id)->where('status', 'pending')->first();
            if ($pro) {
                $activated = ['org_pro', $this->pro->activatePending($pro)];
            } else {
                $prem = PremiumSubscription::where('payment_id', $locked->id)->where('status', 'pending')->first();
                if (!$prem) {
                    return;
                }
                $activated = ['premium', $this->premium->confirmPending($locked)];
            }

            $locked->update([
                'status'           => 'paid',
                'org_confirmed'    => true,
                'org_confirmed_at' => now(),
            ]);
        });

        if ($activated) {
            [$type, $sub] = $activated;
            try {
                $user = User::find($payment->user_id);
                if ($user && $type === 'org_pro') {
                    $this->notifications->createOrganizerProPaidNotification($user, $sub);
                } elseif ($user && $sub->isActive()) {
                    $this->notifications->createPremiumActivatedNotification($user->id, $sub);
                }
            } catch (\Throwable $e) {
                Log::warning('PlatformPaymentService notify failed: ' . $e->getMessage());
            }
            Log::info('PlatformPayment settled', ['payment_id' => $payment->id, 'type' => $type]);
        }

        return $payment->fresh()->status === 'paid';
    }
}
