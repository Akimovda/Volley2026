<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Services\OccurrenceExpansionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class ExpandRecurringEvents extends Command
{
    /**
     * Примеры:
     * php artisan events:expand-recurring
     * php artisan events:expand-recurring --days=90
     * php artisan events:expand-recurring --only=123
     * php artisan events:expand-recurring --dry-run
     */
    protected $signature = 'events:expand-recurring
        {--days=90 : На сколько дней вперёд расширять occurrences (1..365)}
        {--only= : Только для одного event_id}
        {--dry-run : Ничего не записывать (только посчитать/показать)}
        {--force : Игнорировать lock}
        {--chunk=200 : Размер чанка при обходе событий}
        {--maxCreates=500 : Макс occurrences на один event за запуск}';

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

        $chunk = (int) $this->option('chunk');
        if ($chunk < 10) $chunk = 10;
        if ($chunk > 2000) $chunk = 2000;

        $maxCreates = (int) $this->option('maxCreates');
        if ($maxCreates < 1) $maxCreates = 1;
        if ($maxCreates > 5000) $maxCreates = 5000;

        $onlyId = $this->option('only');
        $onlyId = ($onlyId === null || $onlyId === '') ? null : (int) $onlyId;

        $dryRun = (bool) $this->option('dry-run');
        $force  = (bool) $this->option('force');

        $this->info("Recurring events found: (will count after filters)");
        $this->info("Horizon: {$days} days вперед");
        $this->info("Chunk: {$chunk}");
        $this->info("MaxCreates per event: {$maxCreates}");
        if ($dryRun) $this->warn('DRY RUN enabled: no writes.');

        // lock, чтобы cron не запускал параллельно
        $lockKey = 'cmd:events:expand-recurring';
        $lock = null;

        if (!$force) {
            $lock = Cache::lock($lockKey, 300);
            if (!$lock->get()) {
                $this->warn('Another expansion is already running (lock). Use --force to override.');
                return self::SUCCESS;
            }
        }

        try {
            $q = Event::query();

            if (!Schema::hasColumn('events', 'is_recurring')) {
                $this->warn('Column events.is_recurring not found. Nothing to expand.');
                return self::SUCCESS;
            }
            $q->where('is_recurring', true);

            if (Schema::hasColumn('events', 'recurrence_rule')) {
                $q->whereNotNull('recurrence_rule')->where('recurrence_rule', '!=', '');
            }

            if ($onlyId !== null && $onlyId > 0) {
                $q->where('id', $onlyId);
            }

            $total = (clone $q)->count();
            $this->info("Recurring events found: {$total}");

            $expandedEvents = 0;
            $createdTotal   = 0;

            $q->orderBy('id')->chunkById($chunk, function ($events) use (
                $svc, $days, $maxCreates, $dryRun,
                &$expandedEvents, &$createdTotal
            ) {
                foreach ($events as $event) {
                    try {
                        // чтобы не было лишних запросов в сервисе
                        $event->loadMissing('gameSettings');

                        if ($dryRun) {
                            // В dry-run не пишем в БД: просто считаем потенциальное создание через сервис нельзя,
                            // поэтому выводим 0 и предупреждаем (если хочешь — добавим отдельный режим подсчёта).
                            $this->line("event #{$event->id}: created=0 (dry-run)");
                            $expandedEvents++;
                            continue;
                        }

                        $created = (int) $svc->expand($event, $days, $maxCreates);
                        $this->line("event #{$event->id}: created={$created}");

                        $expandedEvents++;
                        $createdTotal += $created;
                    } catch (\Throwable $e) {
                        $this->error("event #{$event->id}: ERROR: " . $e->getMessage());
                    }
                }
            });

            $this->info("Done. Expanded events: {$expandedEvents}");
            $this->info("Occurrences: created={$createdTotal}");

            return self::SUCCESS;
        } finally {
            if ($lock) {
                try { $lock->release(); } catch (\Throwable $e) {}
            }
        }
    }
}