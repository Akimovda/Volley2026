<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\PaymentSetting;
use App\Services\PaymentService;
use App\Services\RegistrationEventLogger;
use App\Services\UserNotificationService;
use App\Services\YookassaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Игрок, автоматически записанный из листа ожидания на платное мероприятие, должен оплатить за
 * config('waitlist.payment_hold_minutes') минут. Не оплатил — выписываем (место уходит следующему
 * в очереди через EventRegistrationObserver). Платёж с «Я оплатил» (ждёт подтверждения организатора)
 * не трогаем; онлайн-платёж перед выпиской перепроверяем у ЮKassa (вебхук мог задержаться).
 */
class ExpireWaitlistPaymentHolds extends Command
{
    protected $signature = 'payments:expire-waitlist-holds {--dry-run : Показать кандидатов без изменений}';
    protected $description = 'Выписать игроков из листа ожидания, не оплативших место за отведённое время';

    public function handle(PaymentService $payments, YookassaService $yookassa, UserNotificationService $notifications): int
    {
        $candidates = Payment::query()
            ->where('status', 'pending')
            ->where('user_confirmed', false)
            ->whereNotNull('registration_id')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get()
            ->filter(fn (Payment $p) => !empty($p->yoomoney_meta['waitlist_hold']));

        if ($this->option('dry-run')) {
            $this->info('Кандидатов: ' . $candidates->count());
            foreach ($candidates as $p) {
                $this->line("payment #{$p->id} reg #{$p->registration_id} user #{$p->user_id} expires {$p->expires_at}");
            }
            return self::SUCCESS;
        }

        $cancelled = 0;
        foreach ($candidates as $candidate) {
            try {
                // Онлайн-платёж мог пройти, а вебхук ещё не дошёл — сверяемся с ЮKassa до выписки
                if ($candidate->method === 'yoomoney' && $candidate->yoomoney_payment_id) {
                    $settings = PaymentSetting::where('organizer_id', $candidate->organizer_id)->first();
                    if ($settings && $yookassa->verifyPayment($candidate->yoomoney_payment_id, $settings) === 'succeeded') {
                        $payments->markPaid($candidate);
                        continue;
                    }
                }

                $reg = null;
                DB::transaction(function () use ($candidate, &$reg) {
                    $payment = Payment::whereKey($candidate->id)->lockForUpdate()->first();
                    if (!$payment || $payment->status !== 'pending' || $payment->user_confirmed) {
                        return;
                    }
                    $payment->update(['status' => 'expired']);

                    // Eloquent save() (не Query Builder) — иначе Observer не вызовет WaitlistService::onSpotFreed()
                    $reg = $payment->registration;
                    if ($reg && !$reg->is_cancelled) {
                        $reg->payment_status = 'expired';
                        $reg->status         = 'cancelled';
                        $reg->is_cancelled   = true;
                        $reg->cancelled_at   = now();
                        $reg->save();
                    } else {
                        $reg = null;
                    }
                });

                if (!$reg) {
                    continue;
                }

                RegistrationEventLogger::log(
                    eventId: (int) $reg->event_id,
                    occurrenceId: (int) $reg->occurrence_id,
                    targetUserId: (int) $reg->user_id,
                    actorId: null,
                    action: 'waitlist_payment_expired',
                    registrationId: (int) $reg->id,
                    meta: ['payment_id' => $candidate->id],
                );

                $event = $reg->occurrence?->event;
                $notifications->createWaitlistPaymentExpiredNotification(
                    userId: (int) $reg->user_id,
                    eventId: (int) $reg->event_id,
                    occurrenceId: (int) $reg->occurrence_id,
                    eventTitle: (string) ($event->title ?? 'мероприятие'),
                    minutes: (int) config('waitlist.payment_hold_minutes', 30),
                );

                $cancelled++;
            } catch (\Throwable $e) {
                Log::error('ExpireWaitlistPaymentHolds error', ['payment_id' => $candidate->id, 'error' => $e->getMessage()]);
            }
        }

        $this->info("ExpireWaitlistPaymentHolds: выписано {$cancelled}.");

        return self::SUCCESS;
    }
}
