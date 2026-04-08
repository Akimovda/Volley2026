<?php
namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\PaymentSetting;
use App\Services\PaymentService;
use App\Services\UserNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentService $paymentService,
        private UserNotificationService $notificationService,
    ) {}

    /**
     * Этап 3 — ЮМани webhook
     * POST /payments/yoomoney/webhook
     */
    public function yoomoneyWebhook(Request $request)
    {
        $body = $request->getContent();
        $data = json_decode($body, true);

        if (empty($data['object']['id'])) {
            return response()->json(['error' => 'invalid payload'], 400);
        }

        $yooPaymentId = $data['object']['id'];
        $status       = $data['object']['status'] ?? '';

        $payment = Payment::where('yoomoney_payment_id', $yooPaymentId)->first();

        if (!$payment) {
            Log::warning("YooMoney webhook: payment not found $yooPaymentId");
            return response()->json(['ok' => true]);
        }

        // Верификация подписи
        $settings = PaymentSetting::where('organizer_id', $payment->organizer_id)->first();
        if ($settings?->yoomoney_secret_key) {
            $ip = $request->ip();
            // ЮМани присылает с определённых IP — можно добавить проверку
        }

        if ($status === 'succeeded') {
            $this->paymentService->markPaid($payment);
            $this->sendPaymentNotification($payment, 'paid');
        } elseif ($status === 'canceled') {
            $payment->update(['status' => 'cancelled']);
            $this->sendPaymentNotification($payment, 'cancelled');
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Этап 4 — Игрок нажал "Я оплатил"
     * POST /payments/{payment}/user-confirm
     */
    public function userConfirm(Request $request, Payment $payment)
    {
        if ($payment->user_id !== $request->user()->id) {
            abort(403);
        }

        if (!$payment->isPending()) {
            return back()->with('error', 'Платёж уже обработан.');
        }

        $this->paymentService->userConfirm($payment);

        // Уведомление организатору
        $this->notificationService->create(
            userId: $payment->organizer_id,
            type: 'payment_user_confirmed',
            title: 'Игрок подтвердил оплату',
            body: 'Игрок #' . $payment->user_id . ' нажал «Я оплатил». Проверьте перевод.',
            payload: [
                'payment_id'    => $payment->id,
                'event_id'      => $payment->event_id,
                'occurrence_id' => $payment->occurrence_id,
                'user_id'       => $payment->user_id,
            ],
            channels: ['in_app', 'telegram', 'vk', 'max'],
        );

        return back()->with('status', '✅ Отметили! Организатор проверит платёж.');
    }

    /**
     * Этап 4 — Организатор подтверждает оплату по ссылке
     * POST /payments/{payment}/org-confirm
     */
    public function orgConfirm(Request $request, Payment $payment)
    {
        if ($payment->organizer_id !== $request->user()->id
            && !$request->user()->isAdmin()) {
            abort(403);
        }

        $this->paymentService->orgConfirm($payment);

        // Уведомление игроку
        $this->sendPaymentNotification($payment, 'paid');

        return back()->with('status', '✅ Оплата подтверждена, игрок добавлен в список.');
    }

    /**
     * Этап 4 — Организатор отклоняет оплату
     * POST /payments/{payment}/org-reject
     */
    public function orgReject(Request $request, Payment $payment)
    {
        if ($payment->organizer_id !== $request->user()->id
            && !$request->user()->isAdmin()) {
            abort(403);
        }

        DB::transaction(function () use ($payment) {
            $payment->update(['status' => 'cancelled']);

            if ($payment->registration_id) {
                \App\Models\EventRegistration::where('id', $payment->registration_id)
                    ->update([
                        'payment_status' => 'cancelled',
                        'is_cancelled'   => true,
                        'cancelled_at'   => now(),
                    ]);
            }
        });

        $this->notificationService->create(
            userId: $payment->user_id,
            type: 'payment_rejected',
            title: 'Оплата не подтверждена',
            body: 'Организатор не подтвердил вашу оплату. Запись отменена.',
            payload: ['payment_id' => $payment->id, 'event_id' => $payment->event_id],
            channels: ['in_app', 'telegram', 'vk', 'max'],
        );

        return back()->with('status', 'Оплата отклонена, запись отменена.');
    }

    /**
     * Этап 5 — Страница транзакций организатора
     * GET /profile/transactions
     */
    public function transactions(Request $request)
    {
        $user = $request->user();

        $payments = Payment::where('organizer_id', $user->id)
            ->with(['user:id,first_name,last_name', 'event:id,title'])
            ->orderByDesc('id')
            ->paginate(20);

        $stats = [
            'total_paid'    => Payment::where('organizer_id', $user->id)->where('status', 'paid')->sum('amount_minor') / 100,
            'total_pending' => Payment::where('organizer_id', $user->id)->where('status', 'pending')->count(),
            'link_pending'  => Payment::where('organizer_id', $user->id)
                ->where('status', 'pending')
                ->whereIn('method', ['tbank_link', 'sber_link'])
                ->where('user_confirmed', true)
                ->where('org_confirmed', false)
                ->count(),
        ];

        return view('payment.transactions', compact('payments', 'stats'));
    }

    /**
     * Этап 6 — Виртуальный кошелёк игрока
     * GET /wallet
     */
    public function wallet(Request $request)
    {
        $user = $request->user();

        $wallets = \App\Models\VirtualWallet::where('user_id', $user->id)
            ->with(['organizer:id,first_name,last_name', 'transactions' => fn($q) => $q->latest()->limit(20)])
            ->get();

        return view('payment.wallet', compact('wallets'));
    }

    /**
     * Возврат средств на виртуальный кошелёк
     */
    public function refund(Request $request, Payment $payment)
    {
        if ($payment->organizer_id !== $request->user()->id && !$request->user()->isAdmin()) {
            abort(403);
        }
        if (!$payment->isPaid()) {
            return back()->with("error", "Возврат возможен только для оплаченных платежей.");
        }
        $event = $payment->event;
        $amount = $event
            ? $this->paymentService->calculateRefundAmount($payment, $event)
            : $payment->amount_minor;

        $this->paymentService->refund($payment, "refund_organizer", $amount);
        $this->sendPaymentNotification($payment, "cancelled");

        return back()->with("status", "↩️ Возврат " . number_format($amount/100, 2) . " ₽ зачислен на виртуальный счёт игрока.");
    }

    private function sendPaymentNotification(Payment $payment, string $event): void
    {
        if ($event === 'paid') {
            $this->notificationService->create(
                userId: $payment->user_id,
                type: 'payment_confirmed',
                title: '✅ Оплата получена!',
                body: 'Ваша оплата подтверждена. Вы в списке участников!',
                payload: ['payment_id' => $payment->id, 'event_id' => $payment->event_id],
                channels: ['in_app', 'telegram', 'vk', 'max'],
            );
        } elseif ($event === 'cancelled') {
            $this->notificationService->create(
                userId: $payment->user_id,
                type: 'payment_cancelled',
                title: '⚠️ Место освобождено',
                body: 'Оплата не получена, ваше место освобождено.',
                payload: ['payment_id' => $payment->id, 'event_id' => $payment->event_id],
                channels: ['in_app', 'telegram', 'vk', 'max'],
            );
        }
    }
}
