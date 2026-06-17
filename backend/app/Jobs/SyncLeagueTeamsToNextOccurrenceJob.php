<?php

namespace App\Jobs;

use App\Models\TournamentLeague;
use App\Models\TournamentSeason;
use App\Models\TournamentSeasonEvent;
use App\Services\TournamentLeagueService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncLeagueTeamsToNextOccurrenceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        private readonly int $seasonId,
        private readonly int $afterRoundNumber,
    ) {}

    public function handle(TournamentLeagueService $leagueService): void
    {
        $season = TournamentSeason::with('leagues')->find($this->seasonId);
        if (!$season) return;

        foreach ($season->leagues as $league) {
            $nextEvent = TournamentSeasonEvent::where('season_id', $this->seasonId)
                ->where('league_id', $league->id)
                ->where('round_number', '>', $this->afterRoundNumber)
                ->where('status', TournamentSeasonEvent::STATUS_PENDING)
                ->whereNull('synced_at')
                ->orderBy('round_number')
                ->first();

            if (!$nextEvent || !$nextEvent->occurrence_id) {
                Log::info("SyncLeagueTeams: нет следующего тура для дивизиона {$league->name} (season {$this->seasonId})");
                continue;
            }

            $occurrence = \App\Models\EventOccurrence::find($nextEvent->occurrence_id);
            if (!$occurrence) continue;

            $result = $leagueService->syncDivisionToOccurrence($league, $occurrence);

            $nextEvent->update(['synced_at' => now()]);

            Log::info("SyncLeagueTeams: дивизион «{$league->name}», тур {$nextEvent->round_number} — добавлено в тур: {$result['linked']}");
        }
    }
}
