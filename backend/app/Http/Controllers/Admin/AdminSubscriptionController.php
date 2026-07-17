<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
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
}
