<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrganizerSubscription;
use App\Models\Payment;
use App\Models\PremiumSubscription;
use App\Models\User;
use App\Services\PremiumService;
use App\Services\UserNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class AdminSubscriptionController extends Controller
{
    public function __construct(private readonly PremiumService $premiumService) {}

    public function confirmPremium(Payment $payment): RedirectResponse
    {
        if ($payment->status === 'paid') {
            return redirect()->route('admin.dashboard')
                ->with('success', '✅ Уже подтверждено ранее.');
        }

        if ($payment->status !== 'pending') {
            return redirect()->route('admin.dashboard')
                ->with('error', 'Платёж не ожидает подтверждения.');
        }

        $subscription = null;
        try {
            $subscription = $this->premiumService->confirmPending($payment);
        } catch (\Throwable $e) {
            Log::error('AdminSubscriptionController::confirmPremium failed: ' . $e->getMessage());
            return redirect()->route('admin.dashboard')
                ->with('error', 'Не удалось найти подписку, связанную с этим платежом.');
        }

        $payment->update([
            'status'           => 'paid',
            'org_confirmed'    => true,
            'org_confirmed_at' => now(),
        ]);

        // Платёж мог зависнуть в pending месяцами — если срок, посчитанный от даты платежа,
        // уже истёк к моменту подтверждения, реального доступа игрок не получает. Сообщать ему
        // «Premium активирован до {дата в прошлом}» в этом случае бессмысленно и вводит в заблуждение.
        if ($subscription->isActive()) {
            try {
                $player = User::find($payment->user_id);
                if ($player) {
                    app(UserNotificationService::class)
                        ->createPremiumActivatedNotification($player->id, $subscription);
                }
            } catch (\Throwable $e) {
                Log::warning('AdminSubscriptionController::confirmPremium notify failed: ' . $e->getMessage());
            }

            return redirect()->route('admin.dashboard')
                ->with('success', '✅ Premium подтверждён и активирован до ' . $subscription->expires_at->format('d.m.Y') . '.');
        }

        return redirect()->route('admin.dashboard')
            ->with('success', '✅ Платёж подтверждён, но срок подписки (от даты платежа) уже истёк — ' . $subscription->expires_at->format('d.m.Y') . '. Игрок не уведомлён, реального доступа не получил.');
    }

    public function deactivatePremium(PremiumSubscription $premiumSubscription): RedirectResponse
    {
        if ($premiumSubscription->status !== 'active') {
            return redirect()->route('admin.dashboard')->with('error', 'Подписка уже не активна.');
        }

        $premiumSubscription->update(['status' => 'cancelled']);

        try {
            $user = User::find($premiumSubscription->user_id);
            if ($user) {
                app(UserNotificationService::class)->create(
                    userId:   $user->id,
                    type:     'premium_deactivated',
                    title:    '⛔ Premium отключён',
                    body:     'Ваша подписка Premium была отключена администратором.',
                    payload:  ['subscription_id' => $premiumSubscription->id],
                    channels: ['in_app', 'telegram', 'vk', 'max'],
                );
            }
        } catch (\Throwable $e) {
            Log::warning('AdminSubscriptionController::deactivatePremium notify failed: ' . $e->getMessage());
        }

        return redirect()->route('admin.dashboard')->with('success', '✅ Premium-подписка деактивирована.');
    }

    public function deactivatePro(OrganizerSubscription $organizerSubscription): RedirectResponse
    {
        if ($organizerSubscription->status !== 'active') {
            return redirect()->route('admin.dashboard')->with('error', 'Подписка уже не активна.');
        }

        $organizerSubscription->update(['status' => 'cancelled']);

        try {
            $user = User::find($organizerSubscription->user_id);
            if ($user) {
                app(UserNotificationService::class)->create(
                    userId:   $user->id,
                    type:     'organizer_pro_deactivated',
                    title:    '⛔ Организатор PRO отключён',
                    body:     'Ваша подписка Организатор PRO была отключена администратором.',
                    payload:  ['subscription_id' => $organizerSubscription->id],
                    channels: ['in_app', 'telegram', 'vk', 'max'],
                );
            }
        } catch (\Throwable $e) {
            Log::warning('AdminSubscriptionController::deactivatePro notify failed: ' . $e->getMessage());
        }

        return redirect()->route('admin.dashboard')->with('success', '✅ PRO-подписка деактивирована.');
    }
}
