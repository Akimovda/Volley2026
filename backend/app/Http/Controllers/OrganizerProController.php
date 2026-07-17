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
        $priceMonth  = (int) ($s?->organizer_pro_month_rub   ?? 499);
        $priceQtr    = (int) ($s?->organizer_pro_quarter_rub ?? 1199);
        $priceYear   = (int) ($s?->organizer_pro_year_rub    ?? 3999);

        $plans = [
            'trial' => [
                'label'    => $trialDays . ' дней',
                'sublabel' => 'Пробный период',
                'price'    => 0,
                'badge'    => 'Бесплатно',
                'features' => ['Свой бот Telegram', 'Виджет на сайт', 'Без рекламы сервиса'],
            ],
            'month' => [
                'label'    => '1 месяц',
                'sublabel' => null,
                'price'    => $priceMonth,
                'badge'    => null,
                'features' => ['Свой бот Telegram и MAX', 'Виджет на сайт', 'Приоритетная поддержка'],
            ],
            'quarter' => [
                'label'    => '3 месяца',
                'sublabel' => $priceQtr < $priceMonth * 3 ? 'Выгода ' . round((1 - $priceQtr / ($priceMonth * 3)) * 100) . '%' : null,
                'price'    => $priceQtr,
                'badge'    => '🔥 Популярный',
                'features' => ['Свой бот Telegram и MAX', 'Виджет на сайт', 'Приоритетная поддержка', 'Расширенная аналитика'],
            ],
            'year' => [
                'label'    => '1 год',
                'sublabel' => $priceYear < $priceMonth * 12 ? 'Выгода ' . round((1 - $priceYear / ($priceMonth * 12)) * 100) . '%' : null,
                'price'    => $priceYear,
                'badge'    => '⭐ Лучшая цена',
                'features' => ['Всё из квартального', 'Персональный менеджер', 'Ранний доступ к новым функциям'],
            ],
        ];

        return view('organizer-pro.index', compact('active', 'plans'));
    }

    /** Временная активация без оплаты (trial / тест) */
    public function activate(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'plan' => ['required', 'string', 'in:trial,month,quarter,year'],
        ]);

        // trial только если ещё не было
        if ($data['plan'] === 'trial') {
            $hadTrial = OrganizerSubscription::query()
                ->where('user_id', $user->id)
                ->where('plan', 'trial')
                ->exists();

            if ($hadTrial) {
                return back()->withErrors(['plan' => 'Пробный период уже использовался.']);
            }
        }

        $sub = $this->service->activate($user, $data['plan']);

        // Фиксируем факт оплаты как Payment — иначе оплата PRO нигде не видна в статистике
        // (activate() создаёт только запись подписки, без payments; см. диагностику 2026-07-17)
        if ((float) $sub->amount_rub > 0) {
            $payment = Payment::create([
                'user_id'           => $user->id,
                'organizer_id'      => $user->id,
                'method'            => 'manual',
                'status'            => 'paid',
                'amount_minor'      => (int) round((float) $sub->amount_rub * 100),
                'currency'          => 'RUB',
                'user_confirmed'    => true,
                'user_confirmed_at' => now(),
                'org_confirmed'     => true,
                'org_confirmed_at'  => now(),
            ]);

            $sub->update(['payment_id' => $payment->id]);
        }

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
}
