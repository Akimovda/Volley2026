<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Services\OccurrenceExpansionService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class ExpandRecurringEvents extends Command
{
    /**
     * Пример:
     * php artisan events:expand-recurring --days=60
     * php artisan events:expand-recurring --days=60 --only=123
     */
    protected $signature = 'events:expand-recurring
        {--days=60 : На сколько дней вперёд расширять occurrences}
        {--only= : Только для одного event_id}
        {--dry-run : Ничего не записывать, только показать что было бы сделано}
        {--force : Игнорировать lock}';

    protected $description = 'Expand recurring events into event_occurrences ahead of time';

    public function handle(OccurrenceExpansionService $svc): int
    {
        if (!Schema::hasTable('event_occurrences')) {
            $this->error('Table event_occurrences does not exist.');
            return self::FAILURE;
        }

        $days = (int) $this->option('days');
        if ($days < 1) $days = 1;
        if ($days > 365) $days = 365;

        $onlyId = $this->option('only');
        $onlyId = ($onlyId === null || $onlyId === '') ? null : (int) $onlyId;

        $dryRun = (bool) $this->option('dry-run');
        $force  = (bool) $this->option('force');

        // Защита от параллельных запусков (cron, руками, supervisor)
        $lockKey = 'cmd:events:expand-recurring';
        if (!$force) {
            $lock = Cache::lock($lockKey, 300);
            if (!$lock->get()) {
                $this->warn('Another expansion is already running (lock). Use --force to override.');
                return self::SUCCESS;
            }
        } else {
            $lock = null;
        }

        try {
            $fromUtc = Carbon::now('UTC')->startOfDay();
            $toUtc   = $fromUtc->copy()->addDays($days)->endOfDay();

            $q = Event::query();

            // Фильтр: только recurring
            if (Schema::hasColumn('events', 'is_recurring')) {
                $q->where('is_recurring', true);
            } else {
                // Если вдруг старое поле отсутствует — смысла расширять нет
                $this->warn('Column events.is_recurring not found. Nothing to expand.');
                return self::SUCCESS;
            }

            // Правило повторения должно быть
            if (Schema::hasColumn('events', 'recurrence_rule')) {
                $q->whereNotNull('recurrence_rule')->where('recurrence_rule', '!=', '');
            }

            if ($onlyId !== null && $onlyId > 0) {
                $q->where('id', $onlyId);
            }

            // Можно чуть ограничить выборку по актуальности,
            // но безопаснее расширять все recurring, которые существуют.
            $total = (clone $q)->count();
            $this->info("Recurring events found: {$total}");
            $this->info("Window: {$fromUtc->toIso8601String()} .. {$toUtc->toIso8601String()}");
            if ($dryRun) $this->warn('DRY RUN enabled: no writes.');

            $expandedEvents = 0;
            $createdTotal   = 0;
            $skippedTotal   = 0;

            $q->orderBy('id')->chunkById(200, function ($events) use (
                $svc, $fromUtc, $toUtc, $dryRun,
                &$expandedEvents, &$createdTotal, &$skippedTotal
            ) {
                foreach ($events as $event) {
                    try {
                        /**
                         * Ожидаем, что сервис вернёт массив вида:
                         * [
                         *   'created' => int,
                         *   'skipped' => int,
                         *   'from' => Carbon|string,
                         *   'to' => Carbon|string,
                         * ]
                         *
                         * Если твой сервис возвращает иначе — просто подправим пару строк ниже.
                         */
                        $res = $svc->expandEvent((int) $event->id, $fromUtc, $toUtc, $dryRun);

                        $created = (int) ($res['created'] ?? 0);
                        $skipped = (int) ($res['skipped'] ?? 0);

                        $expandedEvents++;
                        $createdTotal += $created;
                        $skippedTotal += $skipped;

                        $this->line("event #{$event->id}: created={$created}, skipped={$skipped}");
                    } catch (\Throwable $e) {
                        $this->error("event #{$event->id}: ERROR: ".$e->getMessage());
                        // продолжаем остальные
                    }
                }
            });

            $this->info("Done. Expanded events: {$expandedEvents}");
            $this->info("Occurrences: created={$createdTotal}, skipped={$skippedTotal}");

            return self::SUCCESS;
        } finally {
            if ($lock) {
                try { $lock->release(); } catch (\Throwable $e) {}
            }
        }
    }
}
