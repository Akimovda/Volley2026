<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\UserRestriction;
use App\Services\UserNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BanUnpaidCashPayments extends Command
{
    protected $signature = 'payments:ban-unpaid-cash {--dry-run : Показать кандидатов без бана}';

    protected $description = 'Банит игрока у организатора, если наличный платёж не подтверждён организатором в течение 12 часов после отметки на странице учёта платежей';

    public function handle(UserNotificationService $notificationService): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $payments = Payment::with(['event:id,title,organizer_id', 'user:id,role'])
            ->where('method', 'cash')
            ->where('status', 'pending')
            ->whereNotNull('cash_ban_deadline_at')
            ->where('cash_ban_deadline_at', '<=', now())
            ->whereNull('cash_banned_at')
            ->get()
            // Администраторов не баним никогда, даже случайно (в т.ч. при тестировании фичи самими собой)
            ->reject(fn (Payment $p) => $p->user?->isAdmin());

        if ($dryRun) {
            $this->info("Кандидатов на бан: {$payments->count()}");
            foreach ($payments as $p) {
                $this->line("payment #{$p->id} user #{$p->user_id} event #{$p->event_id} deadline {$p->cash_ban_deadline_at}");
            }
            return self::SUCCESS;
        }

        $count = 0;

        foreach ($payments as $payment) {
            $event = $payment->event;
            $organizerId = (int) ($event->organizer_id ?? $payment->organizer_id);

            if (!$organizerId) {
                continue;
            }

            try {
                DB::transaction(function () use ($payment, $organizerId) {
                    $alreadyBanned = UserRestriction::where('user_id', $payment->user_id)
                        ->where('scope', 'organizer')
                        ->where('organizer_id', $organizerId)
                        ->where(function ($q) {
                            $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
                        })
                        ->exists();

                    if (!$alreadyBanned) {
                        UserRestriction::create([
                            'user_id'      => $payment->user_id,
                            'scope'        => 'organizer',
                            'organizer_id' => $organizerId,
                            'ends_at'      => null,
                            'reason'       => 'Не оплачено участие наличными: payment #' . $payment->id,
                            'created_by'   => null,
                        ]);
                    }

                    $payment->update(['cash_banned_at' => now()]);
                });

                $notificationService->create(
                    userId: $payment->user_id,
                    type: 'cash_payment_banned',
                    title: '⛔ Доступ ограничен',
                    body: 'Вы не оплатили участие в «' . ($event->title ?? 'мероприятии')
                        . '». Доступ к записи на мероприятия этого организатора ограничен до подтверждения оплаты.',
                    payload: ['payment_id' => $payment->id, 'event_id' => $payment->event_id],
                    channels: ['in_app', 'telegram', 'vk', 'max'],
                );

                $count++;
            } catch (\Throwable $e) {
                Log::error("BanUnpaidCashPayments error payment #{$payment->id}: " . $e->getMessage());
            }
        }

        $this->info("BanUnpaidCashPayments: banned {$count} user(s).");

        return self::SUCCESS;
    }
}
