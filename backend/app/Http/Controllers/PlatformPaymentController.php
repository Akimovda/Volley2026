<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\PlatformPaymentSetting;
use App\Services\PlatformPaymentService;
use App\Services\YookassaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/** Онлайн-оплата Организатор Pro / Premium через ЮKassa платформы. */
class PlatformPaymentController extends Controller
{
    public function __construct(
        private YookassaService $yookassa,
        private PlatformPaymentService $settler,
    ) {}

    /** Создаёт платёж ЮKassa для своего pending-платежа и ведёт на страницу оплаты. */
    public function pay(Request $request, Payment $payment)
    {
        $user = $request->user();
        abort_if($payment->user_id !== $user->id || $payment->status !== 'pending', 403);

        $type = $this->settler->typeOf($payment);
        abort_if($type === null, 404);

        $back = $type === 'org_pro' ? route('organizer_pro.index') : route('premium.index');

        if (PlatformPaymentSetting::first()?->method !== 'yoomoney') {
            return redirect($back)->with('error', 'Онлайн-оплата сейчас недоступна.');
        }

        try {
            $description = $type === 'org_pro' ? 'Организатор Pro' : 'Premium';
            $url = $this->yookassa->createPlatformPayment(
                $payment,
                $type,
                $description . ' #' . $payment->id,
                route('platform.payment.return', $payment),
                $user->email,
            );
        } catch (\Throwable $e) {
            Log::error('PlatformPaymentController::pay', ['payment_id' => $payment->id, 'error' => $e->getMessage()]);
            return redirect($back)->with('error', 'Не удалось создать платёж. Попробуйте позже.');
        }

        return redirect()->away($url);
    }

    /** Возврат с оплаты: статус перепроверяем у ЮKassa (вебхук может прийти позже). */
    public function back(Request $request, Payment $payment)
    {
        abort_if($payment->user_id !== $request->user()->id, 403);

        $type = $this->settler->typeOf($payment);
        $back = $type === 'premium' ? route('premium.index') : route('organizer_pro.index');

        if ($payment->status === 'paid') {
            return redirect($back)->with('status', '✅ Оплата получена, подписка активна.');
        }

        if ($payment->yoomoney_payment_id
            && $this->yookassa->verifyPlatformPayment($payment->yoomoney_payment_id) === 'succeeded'
            && $this->settler->settle($payment)) {
            return redirect($back)->with('status', '✅ Оплата получена, подписка активирована.');
        }

        return redirect($back)->with('status', 'Платёж обрабатывается. Подписка включится автоматически сразу после подтверждения оплаты.');
    }
}
