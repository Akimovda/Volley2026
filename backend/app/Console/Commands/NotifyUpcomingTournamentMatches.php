<?php

namespace App\Console\Commands;

use App\Models\TournamentMatch;
use App\Services\TournamentNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class NotifyUpcomingTournamentMatches extends Command
{
    protected $signature = 'tournament:notify-upcoming {--minutes=15}';
    protected $description = 'Уведомить участников о предстоящих матчах турнира';

    public function handle(TournamentNotificationService $notificationService): int
    {
        $minutes = (int) $this->option('minutes');
        $from = Carbon::now('UTC');
        $to = $from->copy()->addMinutes($minutes);

        $matches = TournamentMatch::where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->whereNotNull('team_home_id')
            ->whereNotNull('team_away_id')
            ->whereBetween('scheduled_at', [$from, $to])
            ->whereNull('scored_at') // ещё не уведомляли
            ->with(['teamHome', 'teamAway', 'stage.event'])
            ->get();

        $count = 0;
        foreach ($matches as $match) {
            try {
                $notificationService->notifyUpcomingMatch($match, $minutes);
                $count++;
            } catch (\Throwable $e) {
                $this->error("Match #{$match->id}: " . $e->getMessage());
            }
        }

        $this->info("Notified {$count} upcoming matches.");
        return 0;
    }
}
