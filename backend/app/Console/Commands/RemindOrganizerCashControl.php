<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\EventOccurrence;
use App\Services\UserNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * После начала мероприятия с включённым «Учётом платежей» (cash_payment_tracking_enabled)
 * напоминает организатору проставить отметки об оплате наличными — ссылка сразу ведёт
 * на страницу учёта конкретного мероприятия (payments.event_control).
 */
class RemindOrganizerCashControl extends Command
{
    /** Не сканировать occurrences старше этого возраста — защита от массовой рассылки
     *  напоминаний по старым мероприятиям при первом деплое/сбое расписания. */
    private const MAX_AGE_HOURS = 48;

    protected $signature = 'payments:remind-cash-control {--dry-run} {--limit=200}';

    protected $description = 'Напоминает организатору отметить оплату наличными вскоре после начала мероприятия с включённым учётом платежей';

    public function handle(UserNotificationService $notificationService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit  = max(1, (int) $this->option('limit'));
        $now    = now('UTC');
        $cutoff = $now->copy()->subHours(self::MAX_AGE_HOURS);

        $occIds = DB::table('event_occurrences as eo')
            ->join('events as e', 'e.id', '=', 'eo.event_id')
            ->where('e.cash_payment_tracking_enabled', true)
            ->whereNull('eo.cash_payment_reminder_sent_at')
            ->whereRaw('(eo.is_cancelled IS NULL OR eo.is_cancelled = false)')
            ->where('eo.starts_at', '<=', $now)
            ->where('eo.starts_at', '>=', $cutoff)
            ->orderBy('eo.id')
            ->limit($limit)
            ->pluck('eo.id');

        $this->info("Occurrences to remind: {$occIds->count()}");

        if ($occIds->isEmpty()) {
            return self::SUCCESS;
        }

        $sent = 0;

        foreach ($occIds as $occId) {
            $occurrence = EventOccurrence::with('event')->find($occId);
            $event = $occurrence?->event;

            if (!$occurrence || !$event || !$event->organizer_id) {
                continue;
            }

            if ($dryRun) {
                $this->line("occurrence #{$occId} event #{$event->id} organizer #{$event->organizer_id}");
                continue;
            }

            try {
                $controlUrl = route('payments.event_control', ['event' => $event->id, 'occurrence' => $occurrence->id]);

                $notificationService->create(
                    userId: (int) $event->organizer_id,
                    type: 'cash_payment_control_reminder',
                    title: '💰 Отметьте оплату',
                    body: 'Прошу Вас отметить оплату игроков в «' . $event->title . '»!',
                    payload: [
                        'event_id'      => $event->id,
                        'occurrence_id' => $occurrence->id,
                        'button_text'   => 'Отметить оплату',
                        'button_url'    => $controlUrl,
                    ],
                    channels: ['in_app', 'telegram', 'vk', 'max'],
                );

                $occurrence->update(['cash_payment_reminder_sent_at' => now()]);
                $sent++;
            } catch (\Throwable $e) {
                Log::error("RemindOrganizerCashControl error occurrence #{$occId}: " . $e->getMessage());
            }
        }

        if (!$dryRun) {
            $this->info("Reminders sent: {$sent}");
        }

        return self::SUCCESS;
    }
}
