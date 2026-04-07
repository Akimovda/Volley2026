<?php

namespace App\Console\Commands;

use App\Jobs\BotAssistantJob;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BotAssistCommand extends Command
{
    protected $signature = 'bot:assist
                            {--dry-run : Показать что будет сделано, без реальных изменений}
                            {--occurrence= : Обработать только конкретный occurrence_id}';

    protected $description = 'Запускает «Помощник записи» — управляет ботами на мероприятиях';

    public function handle(): int
    {
        $dryRun      = $this->option('dry-run');
        $occurrenceId = $this->option('occurrence');

        if ($dryRun) {
            $this->warn('⚠ DRY-RUN режим: реальных изменений не будет');
        }

        // Выбираем occurrences где:
        // - bot_assistant_enabled = true
        // - мероприятие ещё не началось (или начинается не раньше чем через 3 часа)
        // - запись открыта
        $query = DB::table('event_occurrences as eo')
            ->join('events as e', 'e.id', '=', 'eo.event_id')
            ->where('e.bot_assistant_enabled', true)
            ->where('eo.starts_at', '>', Carbon::now()->addHours(3)->utc())
            ->select('eo.id as occurrence_id', 'e.id as event_id', 'e.title', 'eo.starts_at');

        // Только открытая запись
        $query->where(function ($q) {
            $q->whereNull('eo.registration_starts_at')
              ->orWhere('eo.registration_starts_at', '<=', Carbon::now());
        });
        
        $query->where(function ($q) {
            $q->whereNull('eo.registration_ends_at')
              ->orWhere('eo.registration_ends_at', '>=', Carbon::now());
        });

        if ($occurrenceId) {
            $query->where('eo.id', (int) $occurrenceId);
        }

        $occurrences = $query->get();

        if ($occurrences->isEmpty()) {
            $this->info('Нет активных мероприятий с «Помощником записи».');
            return self::SUCCESS;
        }

        $this->info("Найдено мероприятий: {$occurrences->count()}");

        foreach ($occurrences as $occ) {
            $startsAt = Carbon::parse($occ->starts_at)->setTimezone('Europe/Moscow')->format('d.m.Y H:i');
            $this->line("  → [occurrence #{$occ->occurrence_id}] {$occ->title} ({$startsAt})");

            if (!$dryRun) {
                BotAssistantJob::dispatch((int) $occ->occurrence_id)
                    ->onQueue('default');
            }
        }

        if (!$dryRun) {
            $this->info('✅ Задачи поставлены в очередь.');
        }

        return self::SUCCESS;
    }
}
