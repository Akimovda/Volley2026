<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\TournamentSeason;
use App\Models\TournamentSeasonEvent;
use App\Services\TournamentSeasonAutoCreateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncTournamentSeasonRouting extends Command
{
    protected $signature = 'tournaments:sync-season-routing
                            {--event=  : ID конкретного события (иначе все recurring tournament)}
                            {--dry-run : Показать что изменится без сохранения}';

    protected $description = 'Перепривязать occurrence турниров к сезонам по датам (обратная сила)';

    public function handle(TournamentSeasonAutoCreateService $service): int
    {
        $eventId = $this->option('event');
        $dryRun  = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN — изменения не сохраняются');
        }

        $query = Event::where('format', 'tournament')
            ->whereNotNull('season_id')
            ->where('is_recurring', true);

        if ($eventId) {
            $query->where('id', (int) $eventId);
        }

        $events = $query->get();

        if ($events->isEmpty()) {
            $this->info('Нет подходящих событий.');
            return 0;
        }

        foreach ($events as $event) {
            $this->line("Event #{$event->id}: {$event->title}");

            if ($dryRun) {
                $this->showDiff($event);
                continue;
            }

            $result = $service->syncAllOccurrencesToCorrectSeasons($event);

            $event->refresh();
            $this->info(sprintf(
                '  создано=%d  перемещено=%d  без_сезона=%d  season_id=%d',
                $result['created'],
                $result['moved'],
                $result['skipped'],
                $event->season_id ?? 0,
            ));
        }

        $this->info('Готово.');
        return 0;
    }

    private function showDiff(Event $event): void
    {
        $currentSeason = TournamentSeason::find($event->season_id);
        if (!$currentSeason || !$currentSeason->league_id) {
            $this->line('  [skip] нет league_id у текущего сезона');
            return;
        }

        $parentLeagueId = (int) $currentSeason->league_id;
        $tz = $event->timezone ?: 'UTC';

        $occurrences = $event->occurrences()
            ->whereNull('cancelled_at')
            ->orderBy('starts_at')
            ->get();

        foreach ($occurrences as $occ) {
            $localDate = \Carbon\Carbon::parse($occ->starts_at, 'UTC')
                ->setTimezone($tz)
                ->toDateString();

            $existing = TournamentSeasonEvent::where('occurrence_id', $occ->id)->first();
            $existingSeasonId = $existing?->season_id;

            // Ищем нужный сезон напрямую через запрос
            $targetSeason = TournamentSeason::where('league_id', $parentLeagueId)
                ->where('status', '!=', TournamentSeason::STATUS_DRAFT)
                ->whereDate('starts_at', '<=', $localDate)
                ->whereDate('ends_at', '>=', $localDate)
                ->orderBy('starts_at', 'desc')
                ->first();

            if (!$targetSeason) {
                $this->line("  occ #{$occ->id} ({$localDate}) → нет сезона" . ($existing ? " [удалить из сезона #{$existingSeasonId}]" : ' [пропустить]'));
                continue;
            }

            if ($existingSeasonId && (int) $existingSeasonId === (int) $targetSeason->id) {
                $this->line("  occ #{$occ->id} ({$localDate}) → OK (сезон #{$targetSeason->id})");
            } elseif ($existingSeasonId) {
                $this->warn("  occ #{$occ->id} ({$localDate}) → переместить сезон #{$existingSeasonId} → #{$targetSeason->id} ({$targetSeason->name})");
            } else {
                $this->line("  occ #{$occ->id} ({$localDate}) → создать в сезоне #{$targetSeason->id} ({$targetSeason->name})");
            }
        }
    }
}
