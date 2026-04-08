<?php
namespace App\Services;

use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\EventRegistration;
use App\Models\Payment;
use App\Models\PaymentSetting;
use App\Models\VirtualWallet;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    /**
     * Создать платёж при записи на мероприятие
     */
    public function createForRegistration(
        EventRegistration $registration,
        Event $event,
        EventOccurrence $occurrence
    ): Payment {
        $method      = $event->payment_method ?? 'cash';
        $amountMinor = (int) ($event->price_minor ?? 0);
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
        });
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

                if ($payment->registration_id) {
                    EventRegistration::where('id', $payment->registration_id)->update([
                        'payment_status' => 'expired',
                        'is_cancelled'   => true,
                        'cancelled_at'   => now(),
                    ]);
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
