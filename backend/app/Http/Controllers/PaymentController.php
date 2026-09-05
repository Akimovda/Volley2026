<?php
namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\Payment;
use App\Models\PaymentSetting;
use App\Services\CashPaymentTrackingService;
use App\Services\PaymentService;
use App\Services\UserNotificationService;
use App\Services\YookassaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentService $paymentService,
        private UserNotificationService $notificationService,
        private YookassaService $yookassa,
        private CashPaymentTrackingService $cashTracking,
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

        $payment = Payment::where('yoomoney_payment_id', $yooPaymentId)->first();

        if (!$payment) {
            Log::warning("YooMoney webhook: payment not found $yooPaymentId");
            return response()->json(['ok' => true]);
        }

        $settings = PaymentSetting::where('organizer_id', $payment->organizer_id)->first();
        if (!$settings) {
            Log::warning("YooMoney webhook: no payment_settings for organizer {$payment->organizer_id}");
            return response()->json(['ok' => true]);
        }

        // НЕ доверяем статусу из тела вебхука напрямую (его теоретически можно подделать) —
        // переспрашиваем реальный статус платежа у ЮKassa тем же способом, что и для
        // рекламных платежей (YookassaService::handleWebhook()).
        $status = $this->yookassa->verifyPayment($yooPaymentId, $settings);
        if ($status === null) {
            return response()->json(['ok' => true]);
        }

        if ($status === 'succeeded' && $payment->status === 'pending') {
            $this->paymentService->markPaid($payment);

            if ($payment->court_booking_id) {
                $booking = $payment->courtBooking()->with('court.direction.location')->first();
                if ($booking && $booking->court?->direction?->location?->owner_id) {
                    $this->notificationService->createCourtBookingPaidNotification(
                        $booking->court->direction->location->owner_id,
                        $booking
                    );
                }
            } else {
                $this->sendPaymentNotification($payment, 'paid');
            }
        } elseif ($status === 'canceled' && $payment->status === 'pending') {
            $payment->update(['status' => 'cancelled']);
            if (!$payment->court_booking_id) {
                $this->sendPaymentNotification($payment, 'cancelled');
            }
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
            ->with(['user:id,first_name,last_name', 'event:id,title,payment_method,cash_payment_tracking_enabled'])
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

        $hasCashTracking = Event::where('organizer_id', $user->id)
            ->where('cash_payment_tracking_enabled', true)
            ->exists();

        return view('payment.transactions', compact('payments', 'stats', 'hasCashTracking'));
    }

    /**
     * Список мероприятий с включённым «Учётом платежей» — точка входа для организатора,
     * чтобы быстро найти нужное мероприятие и перейти к отметке оплаты (см. eventPaymentControl).
     * GET /profile/transactions/cash-control
     */
    public function cashControlIndex(Request $request)
    {
        $user = $request->user();
        $now = now('UTC');
        $cutoff = $now->copy()->subHours(24);

        $occurrences = EventOccurrence::query()
            ->whereHas('event', function ($q) use ($user) {
                $q->where('organizer_id', $user->id)
                    ->where('cash_payment_tracking_enabled', true);
            })
            ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
            // Есть активная регистрация — иначе мероприятию нечего контролировать
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('event_registrations as er')
                    ->whereColumn('er.occurrence_id', 'event_occurrences.id')
                    ->whereRaw('(er.is_cancelled IS NULL OR er.is_cancelled = false)')
                    ->where('er.status', '!=', 'cancelled');
            })
            // Ещё не завершилось ИЛИ завершилось не позже 24ч назад (дальше уже
            // подхватывает payments:process-unattended-cash — в списке делать нечего)
            ->whereRaw('starts_at + make_interval(secs => COALESCE(duration_sec, 0)) >= ?', [$cutoff])
            ->with(['event:id,title,location_id', 'event.location:id,name', 'location:id,name'])
            // Сначала прошедшие (нужно действие быстрее всех — 24ч дедлайн), потом
            // текущие/будущие; внутри группы — по дате.
            ->orderByRaw('(starts_at + make_interval(secs => COALESCE(duration_sec, 0)) <= ?) DESC', [$now])
            ->orderBy('starts_at')
            ->paginate(30);

        return view('payment.cash_control_index', compact('occurrences'));
    }

    /**
     * Учёт наличных платежей — страница организатора по конкретному туру.
     * GET /profile/transactions/{event}?occurrence={occurrence}
     */
    public function eventPaymentControl(Request $request, Event $event)
    {
        $user = $request->user();
        if ((int) $event->organizer_id !== (int) $user->id && !$user->isAdmin()) {
            abort(403);
        }

        if (!$event->cash_payment_tracking_enabled) {
            return redirect()->route('profile.transactions')
                ->with('error', 'Для этого мероприятия не включён «Учёт платежей».');
        }

        $occurrenceId = (int) $request->query('occurrence');
        $occurrence = $occurrenceId
            ? EventOccurrence::where('id', $occurrenceId)->where('event_id', $event->id)->first()
            : null;

        if (!$occurrence) {
            return redirect()->route('profile.transactions')
                ->with('error', 'Тур мероприятия не найден.');
        }

        $rows = $this->cashTracking->getTrackingRows($event, $occurrence);

        return view('payment.event_control', compact('event', 'occurrence', 'rows'));
    }

    /**
     * Сохранение отметок «оплатил» — единственная точка, где статус наличного
     * платежа становится подтверждённым. POST /profile/transactions/{event}
     */
    public function eventPaymentControlSave(Request $request, Event $event)
    {
        $user = $request->user();
        if ((int) $event->organizer_id !== (int) $user->id && !$user->isAdmin()) {
            abort(403);
        }

        if (!$event->cash_payment_tracking_enabled) {
            abort(403);
        }

        $data = $request->validate([
            'occurrence_id'   => ['required', 'integer'],
            'paid_user_ids'   => ['nullable', 'array'],
            'paid_user_ids.*' => ['integer'],
        ]);

        $occurrence = EventOccurrence::where('id', $data['occurrence_id'])
            ->where('event_id', $event->id)
            ->firstOrFail();

        $result = $this->cashTracking->save($event, $occurrence, $data['paid_user_ids'] ?? [], $user);

        return redirect()
            ->route('payments.event_control', ['event' => $event->id, 'occurrence' => $occurrence->id])
            ->with('status', "Сохранено: подтверждено оплат — {$result['confirmed']}, отправлено напоминаний — {$result['reminded']}.");
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
