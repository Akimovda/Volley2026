<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\OrganizerSubscription;
use App\Models\Payment;
use App\Models\PlatformPaymentSetting;
use App\Models\User;
use App\Services\OrganizerSubscriptionService;
use App\Services\UserNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class OrganizerProController extends Controller
{
    public function __construct(
        private readonly OrganizerSubscriptionService $service
    ) {}

    public function index(Request $request): View
    {
        $user   = $request->user();
        $active = $user ? $this->service->getActive($user) : null;

        $s = \App\Models\PlatformPaymentSetting::first();

        $trialDays   = (int) ($s?->organizer_pro_trial_days ?? 7);
        $priceHalf   = (int) ($s?->organizer_pro_half_rub    ?? 3490);
        $priceYear   = (int) ($s?->organizer_pro_year_rub    ?? 4990);

        $plans = [
            'trial' => [
                'label'    => $trialDays . ' дней',
                'sublabel' => 'Пробный период',
                'price'    => 0,
                'badge'    => 'Бесплатно',
                'features' => ['Свой бот Telegram', 'Виджет на сайт', 'Аналитика игроков и турниров', 'Без рекламы сервиса'],
            ],
            'half' => [
                'label'    => '6 месяцев',
                'sublabel' => null,
                'price'    => $priceHalf,
                'badge'    => null,
                'features' => ['Свой бот Telegram и MAX', 'Виджет на сайт', 'Аналитика игроков и турниров', 'Приоритетная поддержка'],
            ],
            'year' => [
                'label'    => '1 год',
                'sublabel' => $priceYear < $priceHalf * 2 ? 'Выгода ' . round((1 - $priceYear / ($priceHalf * 2)) * 100) . '%' : null,
                'price'    => $priceYear,
                'badge'    => '⭐ Лучшая цена',
                'features' => ['Всё из 6-месячного', 'Персональный менеджер', 'Ранний доступ к новым функциям'],
            ],
        ];

        $pending         = $user ? $this->service->getPending($user) : null;
        $pendingPayment  = $pending?->payment_id ? Payment::find($pending->payment_id) : null;
        $platformPayment = $s;

        return view('organizer-pro.index', compact('active', 'plans', 'pending', 'pendingPayment', 'platformPayment'));
    }

    /**
     * Самоактивация — ТОЛЬКО пробный период (один раз). Платные тарифы включаются после подтверждённой оплаты
     * (pay() → «Я оплатил» → подтверждение админом) либо вручную из админки.
     */
    public function activate(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            'plan' => ['required', 'string', 'in:trial'],
        ]);

        $hadTrial = OrganizerSubscription::query()
            ->where('user_id', $user->id)
            ->where('plan', 'trial')
            ->exists();

        if ($hadTrial) {
            return back()->withErrors(['plan' => 'Пробный период уже использовался.']);
        }

        $sub = $this->service->activate($user, 'trial');

        try {
            $platSettings   = PlatformPaymentSetting::first();
            $paymentAdminId = (int) ($platSettings?->payment_admin_id ?? 1);
            $admin = User::find($paymentAdminId) ?? User::where('role', 'admin')->first();

            if ($admin) {
                app(UserNotificationService::class)
                    ->createOrganizerProActivatedNotification($admin, $sub, $user);
            }
        } catch (\Throwable $e) {
            Log::warning('OrganizerProController activate notify failed: ' . $e->getMessage());
        }

        return redirect()
            ->route('organizer_pro.index')
            ->with('status', '✅ Организатор Pro активирован до ' . $sub->expires_at->format('d.m.Y') . '!');
    }

    /** Заявка на платный тариф: создаём pending-подписку и платёж, дальше — оплата и «Я оплатил». */
    public function pay(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'plan' => ['required', 'string', 'in:half,year'],
        ]);

        if (!PlatformPaymentSetting::first()) {
            return back()->withErrors(['plan' => 'Оплата временно недоступна. Попробуйте позже.']);
        }

        [$sub, $payment, $created] = $this->service->createPending($user, $data['plan']);

        if (!$created) {
            return redirect()->route('organizer_pro.index')
                ->with('status', 'Ваша оплата уже отправлена на проверку — администратор подтвердит её в ближайшее время.');
        }

        return redirect()->route('organizer_pro.index')->with('payment_pending', $payment->id);
    }

    /** Пользователь нажал «Я оплатил» — уведомляем администратора, подписку включает админ после проверки. */
    public function confirmPayment(Request $request, Payment $payment): RedirectResponse
    {
        $user = $request->user();

        $sub = OrganizerSubscription::query()
            ->where('payment_id', $payment->id)
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if ($payment->user_id !== $user->id || !$sub || $payment->status !== 'pending') {
            abort(403);
        }

        if (!$payment->user_confirmed) {
            $payment->update([
                'user_confirmed'    => true,
                'user_confirmed_at' => now(),
            ]);

            try {
                $platSettings   = PlatformPaymentSetting::first();
                $paymentAdminId = (int) ($platSettings?->payment_admin_id ?? 1);
                $admin = User::find($paymentAdminId) ?? User::where('role', 'admin')->first();

                if ($admin) {
                    app(UserNotificationService::class)
                        ->createOrganizerProPaymentPendingNotification($admin, $payment, $user, $sub);
                }
            } catch (\Throwable $e) {
                Log::warning('OrganizerProController confirmPayment notify failed: ' . $e->getMessage());
            }
        }

        return redirect()->route('organizer_pro.index')
            ->with('status', '✅ Спасибо! Проверим перевод и активируем Организатор Pro — обычно это занимает немного времени.');
    }
}
