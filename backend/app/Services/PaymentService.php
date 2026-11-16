<?php
namespace App\Services;

use App\Models\CourtBooking;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\EventRegistration;
use App\Models\Payment;
use App\Models\PaymentSetting;
use App\Models\VirtualWallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    public function __construct(
        private YookassaService $yookassa,
    ) {}

    /**
     * Создать (или переиспользовать активный) платёж ЮKassa за бронь корта — используется
     * "Оплатить" на /my/bookings. Ключи берутся из payment_settings ВЛАДЕЛЬЦА ЛОКАЦИИ.
     */
    public function createForBooking(CourtBooking $booking, PaymentSetting $settings, string $returnUrl): Payment
    {
        $existing = Payment::where('court_booking_id', $booking->id)
            ->where('status', 'pending')
            ->whereNotNull('yoomoney_confirmation_url')
            ->latest('id')
            ->first();

        if ($existing && !$existing->isExpired()) {
            return $existing;
        }

        $result = $this->yookassa->createBookingPayment($booking, $settings, $returnUrl);

        return Payment::create([
            'user_id'                   => $booking->user_id,
            'organizer_id'               => $settings->organizer_id,
            'court_booking_id'           => $booking->id,
            'method'                     => 'yoomoney',
            'status'                     => 'pending',
            'amount_minor'               => (int) round($booking->price_total * 100),
            'currency'                   => 'RUB',
            'yoomoney_payment_id'        => $result['payment_id'],
            'yoomoney_confirmation_url'  => $result['payment_url'],
            'expires_at'                 => $booking->expires_at,
        ]);
    }
    /**
     * Платёж за заказ из виджета (общий для нескольких броней). Строка payments хранится на первой брони,
     * остальные находятся по order_token (см. paidPaymentForBooking).
     *
     * @param \Illuminate\Support\Collection<int, CourtBooking> $bookings
     */
    public function createForOrder($bookings, PaymentSetting $settings, string $returnUrl): Payment
    {
        $first = $bookings->first();

        $existing = Payment::where('court_booking_id', $first->id)->where('status', 'pending')
            ->whereNotNull('yoomoney_confirmation_url')->latest('id')->first();
        if ($existing && !$existing->isExpired()) {
            return $existing;
        }

        $result = $this->yookassa->createOrderPayment(
            $bookings, $settings, $returnUrl,
            (string) $first->guest_email, (string) $first->guest_phone, (string) $first->guest_name
        );

        return Payment::create([
            'user_id'                   => $settings->organizer_id, // гость без аккаунта: payments.user_id NOT NULL, подставляем арендодателя
            'organizer_id'              => $settings->organizer_id,
            'court_booking_id'          => $first->id,
            'method'                    => 'yoomoney',
            'status'                    => 'pending',
            'amount_minor'              => (int) round($bookings->sum(fn ($b) => (float) $b->price_total) * 100),
            'currency'                  => 'RUB',
            'yoomoney_payment_id'       => $result['payment_id'],
            'yoomoney_confirmation_url' => $result['payment_url'],
            'expires_at'                => $first->expires_at,
        ]);
    }

    /** Оплаченный платёж, покрывающий бронь: свой или общий платёж заказа из виджета. */
    public function paidPaymentForBooking(CourtBooking $booking): ?Payment
    {
        $own = Payment::where('court_booking_id', $booking->id)->whereIn('status', ['paid', 'partially_refunded'])->latest('id')->first();
        if ($own || !$booking->order_token) {
            return $own;
        }

        $ids = CourtBooking::where('order_token', $booking->order_token)->pluck('id');

        return Payment::whereIn('court_booking_id', $ids)->whereIn('status', ['paid', 'partially_refunded'])->latest('id')->first();
    }

    /**
     * Создать платёж при записи на мероприятие
     */
    public function createForRegistration(
        EventRegistration $registration,
        Event $event,
        EventOccurrence $occurrence,
        ?int $overrideAmountMinor = null
    ): Payment {
        $method      = $event->payment_method ?? 'cash';
        $amountMinor = $overrideAmountMinor ?? (int) ($event->price_minor ?? 0);
        $organizerId = (int) $event->organizer_id;
        $userId      = (int) $registration->user_id;

        $settings = PaymentSetting::where('organizer_id', $organizerId)->first();
        $holdMin  = $settings?->payment_hold_minutes ?? 15;

        // Организатор, записавшийся на собственное мероприятие, не платит сам себе —
        // платёж сразу считается подтверждённым, независимо от способа оплаты.
        $isOrganizerSelf = $organizerId > 0 && $userId === $organizerId;

        // Комиссия/налог «сверху» (настройки организатора) — только для онлайн-оплаты.
        $feeMinor = $taxMinor = 0;
        if ($method === 'yoomoney' && !$isOrganizerSelf && $amountMinor > 0) {
            $baseMinor   = $amountMinor;
            $priced      = \App\Support\PaymentPricing::apply($amountMinor, $settings);
            $amountMinor = $priced['total'];
            $feeMinor    = $priced['fee'];
            $taxMinor    = $priced['tax'];
        }

        // Повторный вызов для той же регистрации (cancel+rejoin реактивирует старую
        // строку и снова создаёт платёж) иначе оставляет старый Payment осиротевшим
        // в status=pending навсегда — его не видно на /profile/transactions/{event}
        // (там берётся latest()), но payments:process-unattended-cash / ban-unpaid-cash
        // сканируют ВСЕ pending-платежи напрямую и банили бы игрока за уже оплаченное.
        Payment::where('registration_id', $registration->id)
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);

        $payment = Payment::create([
            'user_id'         => $userId,
            'organizer_id'    => $organizerId,
            'event_id'        => $event->id,
            'occurrence_id'   => $occurrence->id,
            'registration_id' => $registration->id,
            'method'          => $method,
            'status'          => $isOrganizerSelf ? 'paid' : 'pending',
            'amount_minor'    => $amountMinor,
            'fee_minor'       => $feeMinor,
            'tax_minor'       => $taxMinor,
            'yoomoney_meta'   => isset($baseMinor) ? ['base_minor' => $baseMinor] : null,
            'currency'        => $event->price_currency ?? 'RUB',
            'org_confirmed'    => $isOrganizerSelf,
            'org_confirmed_at' => $isOrganizerSelf ? now() : null,
            'expires_at'      => (!$isOrganizerSelf && in_array($method, ['yoomoney']))
                ? now()->addMinutes($holdMin)
                : null,
        ]);

        // Онлайн-оплата: реальный платёж ЮKassa по ключам организатора (ссылка — в yoomoney_confirmation_url,
        // кнопку «Оплатить» рисует events/show/players.blade.php). Сбой не должен ронять запись на мероприятие.
        if ($method === 'yoomoney' && !$isOrganizerSelf && $amountMinor > 0 && $settings?->isYoomoneyReady()
            && !\App\Support\PaymentPricing::needsChoice($settings)) {
            try {
                $this->yookassa->createEventPayment(
                    $payment,
                    $event,
                    $settings,
                    route('events.show', $event->id) . '?occurrence=' . $occurrence->id,
                );
            } catch (\Throwable $e) {
                Log::error('PaymentService: createEventPayment failed', [
                    'payment_id' => $payment->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        // Обновляем регистрацию
        $registration->update([
            'payment_status'   => $isOrganizerSelf ? 'paid' : 'pending',
            'payment_id'       => $payment->id,
            'payment_expires_at' => $payment->expires_at,
        ]);

        return $payment;
    }

    /**
     * Подтвердить оплату (ЮМани webhook или ручное подтверждение)
     */
    public function markPaid(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $payment->update(['status' => 'paid']);

            if ($payment->registration_id) {
                EventRegistration::where('id', $payment->registration_id)->update([
                    'payment_status'    => 'paid',
                    'payment_expires_at' => null,
                ]);
            }

            if ($payment->court_booking_id) {
                $token = CourtBooking::where('id', $payment->court_booking_id)->value('order_token');
                CourtBooking::where(fn ($q) => $q->where('id', $payment->court_booking_id)->when($token, fn ($qq) => $qq->orWhere('order_token', $token)))
                    ->where('status', CourtBooking::STATUS_PENDING)
                    ->update(['status' => CourtBooking::STATUS_PAID, 'expires_at' => null]);
            }

            // Командный/индивидуальный платёж турнира — обновить статус команды/участника
            if ($payment->team_id) {
                app(TournamentPaymentService::class)->handleYoomoneyPaid($payment);
            }
        });
    }

    /**
     * Возврат по оплаченной ЮKassa брони корта — настоящий возврат на карту (не VirtualWallet,
     * это для игрока-арендатора, а не для игрока мероприятия). Если брони не была оплачена
     * онлайн (нет yoomoney_payment_id) — возвращать нечего, вызывающая сторона просто отменяет.
     */
    public function refundBooking(Payment $payment, PaymentSetting $settings, string $reason, ?int $amountMinor = null): void
    {
        if (empty($payment->yoomoney_payment_id)) {
            return;
        }

        $already = (int) ($payment->refund_amount_minor ?? 0);
        $amount = $amountMinor ?? ($payment->amount_minor - $already);
        $amount = min($amount, $payment->amount_minor - $already);
        if ($amount <= 0) {
            return;
        }

        $result = $this->yookassa->createRefund(
            $payment->yoomoney_payment_id,
            $amount,
            $settings,
            $reason
        );

        $total = $already + $amount;
        $payment->update([
            'status'              => $total >= $payment->amount_minor ? 'refunded' : 'paid',
            'refund_amount_minor' => $total,
            'refund_reason'       => $reason,
            'refunded_at'         => now(),
        ]);

        Log::info('[PaymentService] Возврат по брони корта оформлен', [
            'payment_id' => $payment->id,
            'refund_id'  => $result['refund_id'],
            'status'     => $result['status'],
        ]);
    }

    /**
     * Пользователь нажал "Я оплатил" (для link-методов)
     */
    public function userConfirm(Payment $payment): void
    {
        $payment->update([
            'user_confirmed'    => true,
            'user_confirmed_at' => now(),
        ]);
    }

    /**
     * Организатор подтвердил оплату по ссылке
     */
    public function orgConfirm(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $payment->update([
                'org_confirmed'    => true,
                'org_confirmed_at' => now(),
            ]);

            if ($payment->isLinkConfirmed()) {
                $this->markPaid($payment);
            }
        });
    }

    /**
     * Освободить просроченные резервы
     */
    public function releaseExpired(): int
    {
        $expired = Payment::expired()->with('registration')->get();
        $count   = 0;

        foreach ($expired as $payment) {
            DB::transaction(function () use ($payment, &$count) {
                $payment->update(['status' => 'expired']);

                // save() а не Query Builder update() — иначе Observer не сработает и waitlist не обработается
                $reg = $payment->registration;
                if ($reg) {
                    $reg->payment_status = 'expired';
                    $reg->is_cancelled   = true;
                    $reg->cancelled_at   = now();
                    $reg->save();
                }
                $count++;
            });
        }

        return $count;
    }

    /**
     * Возврат средств (на виртуальный кошелёк)
     */
    public function refund(
        Payment $payment,
        string $reason = 'refund_organizer',
        ?int $amountMinor = null
    ): void {
        $amount = $amountMinor ?? $payment->amount_minor;

        DB::transaction(function () use ($payment, $reason, $amount) {
            // Если оплачено через ЮМани или link — возвращаем на виртуальный счёт
            if (in_array($payment->method, ['yoomoney', 'tbank_link', 'sber_link', 'wallet'])) {
                $wallet = VirtualWallet::forUserAndOrganizer(
                    $payment->user_id,
                    $payment->organizer_id
                );
                $wallet->credit($amount, $reason, $payment->event_id, $payment->id);
            }

            $payment->update([
                'status'              => 'refunded',
                'refund_amount_minor' => $amount,
                'refund_reason'       => $reason,
                'refunded_at'         => now(),
            ]);

            if ($payment->registration_id) {
                EventRegistration::where('id', $payment->registration_id)
                    ->update(['payment_status' => 'refunded']);
            }
        });
    }

    /**
     * Рассчитать сумму возврата по политике мероприятия
     */
    public function calculateRefundAmount(Payment $payment, Event $event): int
    {
        $settings    = PaymentSetting::where('organizer_id', $event->organizer_id)->first();
        $hoursToEvent = now()->diffInHours($event->starts_at, false);

        $fullHours    = $event->refund_hours_full    ?? $settings?->refund_hours_full    ?? 48;
        $partialHours = $event->refund_hours_partial ?? $settings?->refund_hours_partial ?? 24;
        $partialPct   = $event->refund_partial_pct   ?? $settings?->refund_partial_pct   ?? 50;

        if ($hoursToEvent >= $fullHours) {
            return $payment->amount_minor; // 100%
        }

        if ($hoursToEvent >= $partialHours) {
            return (int) round($payment->amount_minor * $partialPct / 100);
        }

        return 0; // 0%
    }
}
