<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\PlatformPaymentSetting;
use App\Models\PremiumSubscription;
use App\Services\PremiumService;
use Illuminate\Http\Request;

class PremiumController extends Controller
{
    public function __construct(private PremiumService $premiumService) {}

    public function index()
    {
        $active = auth()->check()
            ? $this->premiumService->getActive(auth()->user())
            : null;

        // Ожидает оплаты
        $pending = auth()->check()
            ? PremiumSubscription::where('user_id', auth()->id())
                ->where('status', 'pending')
                ->with('payment')
                ->latest()
                ->first()
            : null;

        $platformPayment = PlatformPaymentSetting::first();

        return view('premium.index', compact('active', 'pending', 'platformPayment'));
    }

    // Пробный период — активируем сразу
    public function activateTrial(Request $request)
    {
        $user = $request->user();

        if ($this->premiumService->isPremium($user)) {
            return back()->with('error', 'У вас уже есть активный Premium.');
        }

        // Проверяем что пробный ещё не использовался
        $usedTrial = PremiumSubscription::where('user_id', $user->id)
            ->where('plan', 'trial')
            ->exists();

        if ($usedTrial) {
            return back()->with('error', 'Пробный период уже был использован.');
        }

        $sub = $this->premiumService->activate($user, 'trial');

        return back()->with('success', '🎉 Пробный период активирован до ' . $sub->expires_at->format('d.m.Y'));
    }

    // Платные планы — создаём платёж
    public function pay(Request $request)
    {
        $request->validate(['plan' => 'required|in:month,quarter,year']);

        $user = $request->user();
        $plan = $request->plan;

        if ($this->premiumService->isPremium($user)) {
            return back()->with('error', 'У вас уже есть активный Premium.');
        }

        $platformPayment = PlatformPaymentSetting::first();
        if (!$platformPayment) {
            return back()->with('error', 'Оплата временно недоступна. Попробуйте позже.');
        }

        $prices = [
            'month'   => 19900,  // 199₽ в копейках
            'quarter' => 49900,  // 499₽
            'year'    => 169900, // 1699₽
        ];

        // Создаём запись подписки со статусом pending
        $sub = PremiumSubscription::create([
            'user_id'    => $user->id,
            'plan'       => $plan,
            'status'     => 'pending',
            'starts_at'  => now(),
            'expires_at' => now()->addDays(PremiumSubscription::planDays($plan)),
        ]);

        // Создаём платёж
        $payment = Payment::create([
            'user_id'      => $user->id,
            'organizer_id' => null,
            'method'       => $platformPayment->method,
            'status'       => 'pending',
            'amount_minor' => $prices[$plan],
            'currency'     => 'RUB',
        ]);

        $sub->update(['payment_id' => $payment->id]);

        return back()->with('payment_pending', $payment->id);
    }

    // Продление подписки
    public function renew(Request $request)
    {
        $request->validate(['plan' => 'required|in:month,quarter,year']);

        $user = $request->user();
        $plan = $request->plan;

        $platformPayment = PlatformPaymentSetting::first();
        if (!$platformPayment) {
            return back()->with('error', 'Оплата временно недоступна.');
        }

        $prices = [
            'month'   => 19900,
            'quarter' => 49900,
            'year'    => 169900,
        ];

        // Создаём pending-подписку для продления
        $sub = PremiumSubscription::create([
            'user_id'    => $user->id,
            'plan'       => $plan,
            'status'     => 'pending',
            'starts_at'  => now(),
            'expires_at' => now()->addDays(PremiumSubscription::planDays($plan)),
        ]);

        $payment = Payment::create([
            'user_id'      => $user->id,
            'organizer_id' => null,
            'method'       => $platformPayment->method,
            'status'       => 'pending',
            'amount_minor' => $prices[$plan],
            'currency'     => 'RUB',
        ]);

        $sub->update(['payment_id' => $payment->id]);

        return back()->with('payment_pending', $payment->id);
    }

    // Пользователь нажал "Я оплатил"
    public function confirmPayment(Request $request, Payment $payment)
    {
        $user = $request->user();

        if ($payment->user_id !== $user->id) {
            abort(403);
        }

        $payment->update([
            'user_confirmed'    => true,
            'user_confirmed_at' => now(),
        ]);

        // Уведомляем админа
        \Illuminate\Support\Facades\Log::info("Premium payment user_confirmed: payment #{$payment->id} user #{$user->id}");

        return back()->with('status', '✅ Отлично! Мы проверим перевод и активируем Premium в течение нескольких минут.');
    }
}
