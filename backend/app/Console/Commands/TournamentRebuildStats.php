<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Services\TournamentStatsService;
use Illuminate\Console\Command;

class TournamentRebuildStats extends Command
{
    protected $signature = 'tournament:rebuild-stats {event_id : ID события}';
    protected $description = 'Пересчитать player_tournament_stats и career stats для турнира';

    public function handle(TournamentStatsService $statsService): int
    {
        $eventId = (int) $this->argument('event_id');
        $event = Event::find($eventId);

        if (! $event) {
            $this->error("Событие {$eventId} не найдено.");
            return 1;
        }

        $this->info("Пересчёт статистики для события #{$eventId}: {$event->title}");

        $statsService->rebuildTournamentStats($event);
        $this->line('  → player_tournament_stats пересчитаны');

        $statsService->rebuildAllCareerStatsForEvent($event);
        $this->line('  → career stats пересчитаны');

        $this->info('Готово.');
        return 0;
    }
}
