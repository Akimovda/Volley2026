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

        $payment = Payment::create([
            'user_id'         => $userId,
            'organizer_id'    => $organizerId,
            'event_id'        => $event->id,
            'occurrence_id'   => $occurrence->id,
            'registration_id' => $registration->id,
            'method'          => $method,
            'status'          => 'pending',
            'amount_minor'    => $amountMinor,
            'currency'        => $event->price_currency ?? 'RUB',
            'expires_at'      => in_array($method, ['yoomoney'])
                ? now()->addMinutes($holdMin)
                : null,
        ]);

        // Обновляем регистрацию
        $registration->update([
            'payment_status'   => 'pending',
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
                CourtBooking::where('id', $payment->court_booking_id)
                    ->where('status', CourtBooking::STATUS_PENDING)
                    ->update(['status' => CourtBooking::STATUS_PAID, 'expires_at' => null]);
            }
        });
    }

    /**
     * Возврат по оплаченной ЮKassa брони корта — настоящий возврат на карту (не VirtualWallet,
     * это для игрока-арендатора, а не для игрока мероприятия). Если брони не была оплачена
     * онлайн (нет yoomoney_payment_id) — возвращать нечего, вызывающая сторона просто отменяет.
     */
    public function refundBooking(Payment $payment, PaymentSetting $settings, string $reason): void
    {
        if (empty($payment->yoomoney_payment_id)) {
            return;
        }

        $result = $this->yookassa->createRefund(
            $payment->yoomoney_payment_id,
            $payment->amount_minor,
            $settings,
            $reason
        );

        $payment->update([
            'status'              => 'refunded',
            'refund_amount_minor' => $payment->amount_minor,
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
