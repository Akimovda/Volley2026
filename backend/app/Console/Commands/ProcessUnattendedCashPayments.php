<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\EventOccurrence;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Services\UserNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Даёт организатору 24 часа после окончания мероприятия на проверку оплаты
 * наличными (/profile/transactions/{event}?occurrence=...), затем:
 *  - если организатор вообще не заходил на страницу учёта (не сохранял форму
 *    ни разу) — считаем всех оплатившими, без банов и без уведомлений;
 *  - если организатор хотя бы раз сохранял форму (частично отметил кого-то) —
 *    существующие отметки не трогаем (история сохраняется), а тем, кого он
 *    так и не отметил, запускаем обычный флоу "не оплатил": дедлайн 12ч +
 *    напоминание (дальше их подхватит payments:ban-unpaid-cash).
 */
class ProcessUnattendedCashPayments extends Command
{
    private const BAN_HOURS = 12;
    private const GRACE_HOURS = 24;

    protected $signature = 'payments:process-unattended-cash {--dry-run : Показать кандидатов без изменений} {--limit=200}';

    protected $description = 'Через 24ч после окончания мероприятия с учётом наличной оплаты: авто-подтверждает оплату (если организатор не открывал учёт) или шлёт напоминание неотмеченным (если открывал частично)';

    public function handle(PaymentService $paymentService, UserNotificationService $notificationService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit  = max(1, (int) $this->option('limit'));
        $now    = now('UTC');
        $cutoff = $now->copy()->subHours(self::GRACE_HOURS);

        $occIds = DB::table('event_occurrences as eo')
            ->join('events as e', 'e.id', '=', 'eo.event_id')
            ->where('e.cash_payment_tracking_enabled', true)
            ->whereNull('eo.cash_payment_autoprocessed_at')
            ->whereRaw('(eo.is_cancelled IS NULL OR eo.is_cancelled = false)')
            ->whereNotNull('eo.duration_sec')
            ->whereRaw('eo.starts_at + make_interval(secs => eo.duration_sec) <= ?', [$cutoff])
            ->orderBy('eo.id')
            ->limit($limit)
            ->pluck('eo.id');

        $this->info("Occurrences to process: {$occIds->count()}");

        if ($occIds->isEmpty()) {
            return self::SUCCESS;
        }

        $autoPaid = 0;
        $reminded = 0;

        foreach ($occIds as $occId) {
            $occurrence = EventOccurrence::with('event')->find($occId);
            $event = $occurrence?->event;

            if (!$occurrence || !$event) {
                continue;
            }

            $wasReviewed = $occurrence->cash_payment_reviewed_at !== null;

            if ($dryRun) {
                $this->line("occurrence #{$occId} reviewed=" . ($wasReviewed ? 'yes' : 'no'));
                continue;
            }

            try {
                DB::transaction(function () use ($occurrence, $event, $wasReviewed, $paymentService, $notificationService, &$autoPaid, &$reminded) {
                    $pendingPayments = Payment::where('occurrence_id', $occurrence->id)
                        ->where('method', 'cash')
                        ->where('status', 'pending')
                        ->get();

                    if (!$wasReviewed) {
                        // Организатор ни разу не открывал учёт — считаем всех оплатившими.
                        foreach ($pendingPayments as $payment) {
                            $paymentService->markPaid($payment);
                            $payment->update([
                                'org_confirmed'        => true,
                                'org_confirmed_at'      => now(),
                                'cash_ban_deadline_at' => null,
                                'cash_banned_at'       => null,
                            ]);
                            $autoPaid++;
                        }
                    } else {
                        // Организатор частично отметил — неотмеченным (никогда не
                        // тронутым) шлём напоминание и запускаем дедлайн бана.
                        $untouched = $pendingPayments->whereNull('cash_ban_deadline_at')->whereNull('cash_banned_at');
                        foreach ($untouched as $payment) {
                            $payment->update([
                                'cash_ban_deadline_at' => now()->addHours(self::BAN_HOURS),
                            ]);

                            $notificationService->create(
                                userId: $payment->user_id,
                                type: 'cash_payment_reminder',
                                title: '⚠️ Требуется оплата',
                                body: 'Организатор ещё не подтвердил вашу оплату участия в «' . $event->title . '». '
                                    . 'Оплатите в течение ' . self::BAN_HOURS . ' часов, иначе доступ к записи на мероприятия этого организатора будет ограничен.',
                                payload: ['payment_id' => $payment->id, 'event_id' => $event->id, 'occurrence_id' => $occurrence->id],
                                channels: ['in_app', 'telegram', 'vk', 'max'],
                            );
                            $reminded++;
                        }
                    }

                    $occurrence->update(['cash_payment_autoprocessed_at' => now()]);
                });
            } catch (\Throwable $e) {
                Log::error("ProcessUnattendedCashPayments error occurrence #{$occId}: " . $e->getMessage());
            }
        }

        if (!$dryRun) {
            $this->info("Auto-confirmed paid: {$autoPaid}, reminded: {$reminded}");
        }

        return self::SUCCESS;
    }
}
