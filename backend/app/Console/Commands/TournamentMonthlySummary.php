<?php

namespace App\Console\Commands;

use App\Models\TournamentSeason;
use App\Models\TournamentSeasonEvent;
use App\Models\TournamentSeasonStats;
use App\Models\PlayerTournamentStats;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TournamentMonthlySummary extends Command
{
    protected $signature = 'tournament:monthly-summary
                            {--month= : Месяц (YYYY-MM), по умолчанию прошлый}
                            {--season= : ID конкретного сезона}';

    protected $description = 'Пересчитать сезонную статистику и сгенерировать ежемесячную сводку';

    public function handle(): int
    {
        $monthStr = $this->option('month') ?? Carbon::now()->subMonth()->format('Y-m');
        $monthStart = Carbon::createFromFormat('Y-m', $monthStr)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $this->info("Период: {$monthStart->format('d.m.Y')} — {$monthEnd->format('d.m.Y')}");

        // Находим сезоны
        $query = TournamentSeason::where('status', 'active');
        if ($this->option('season')) {
            $query->where('id', $this->option('season'));
        }

        $seasons = $query->with('leagues')->get();

        if ($seasons->isEmpty()) {
            $this->warn('Нет активных сезонов.');
            return 0;
        }

        foreach ($seasons as $season) {
            $this->line("");
            $this->info("Сезон: {$season->name}");

            foreach ($season->leagues as $league) {
                $this->line("  Лига: {$league->name}");

                // Находим завершённые туры за месяц
                $completedEvents = TournamentSeasonEvent::where('season_id', $season->id)
                    ->where('league_id', $league->id)
                    ->where('status', 'completed')
                    ->whereHas('event', function ($q) use ($monthStart, $monthEnd) {
                        $q->whereBetween('created_at', [$monthStart, $monthEnd]);
                    })
                    ->with('event')
                    ->get();

                if ($completedEvents->isEmpty()) {
                    $this->line("    Нет завершённых турниров за месяц");
                    continue;
                }

                $this->line("    Турниров за месяц: {$completedEvents->count()}");

                // Пересчитываем статистику с нуля для точности
                $this->rebuildSeasonStats($season, $league, $completedEvents);

                // Выводим топ-5
                $top = TournamentSeasonStats::where('season_id', $season->id)
                    ->where('league_id', $league->id)
                    ->where('matches_played', '>', 0)
                    ->orderByDesc('match_win_rate')
                    ->limit(5)
                    ->with('user')
                    ->get();

                foreach ($top as $i => $stat) {
                    $name = $stat->user?->name ?? "Игрок #{$stat->user_id}";
                    $this->line("    " . ($i + 1) . ". {$name} — WR: {$stat->match_win_rate}% ({$stat->matches_won}/{$stat->matches_played})");
                }
            }
        }

        $this->info("\n✅ Сводка готова");
        return 0;
    }

    /**
     * Полный пересчёт сезонной статистики.
     */
    protected function rebuildSeasonStats(
        TournamentSeason $season,
        $league,
        $seasonEvents,
    ): void {
        // Собираем все event_id за этот сезон+лигу
        $allSeasonEvents = TournamentSeasonEvent::where('season_id', $season->id)
            ->where('league_id', $league->id)
            ->where('status', 'completed')
            ->pluck('event_id');

        if ($allSeasonEvents->isEmpty()) {
            return;
        }

        // Сбрасываем текущую статистику
        TournamentSeasonStats::where('season_id', $season->id)
            ->where('league_id', $league->id)
            ->delete();

        // Собираем player_tournament_stats по всем турнирам сезона
        $playerStats = PlayerTournamentStats::whereIn('event_id', $allSeasonEvents)
            ->select(
                'user_id',
                DB::raw('COUNT(DISTINCT event_id) as rounds_played'),
                DB::raw('SUM(matches_played) as matches_played'),
                DB::raw('SUM(matches_won) as matches_won'),
                DB::raw('SUM(sets_won) as sets_won'),
                DB::raw('SUM(sets_lost) as sets_lost'),
                DB::raw('SUM(points_scored) as points_scored'),
                DB::raw('SUM(points_conceded) as points_conceded'),
            )
            ->groupBy('user_id')
            ->get();

        foreach ($playerStats as $ps) {
            $matchWr = $ps->matches_played > 0
                ? round($ps->matches_won / $ps->matches_played * 100, 2)
                : 0;

            $totalSets = $ps->sets_won + $ps->sets_lost;
            $setWr = $totalSets > 0
                ? round($ps->sets_won / $totalSets * 100, 2)
                : 0;

            TournamentSeasonStats::create([
                'season_id'       => $season->id,
                'league_id'       => $league->id,
                'user_id'         => $ps->user_id,
                'rounds_played'   => $ps->rounds_played,
                'matches_played'  => $ps->matches_played,
                'matches_won'     => $ps->matches_won,
                'sets_won'        => $ps->sets_won,
                'sets_lost'       => $ps->sets_lost,
                'points_scored'   => $ps->points_scored,
                'points_conceded' => $ps->points_conceded,
                'match_win_rate'  => $matchWr,
                'set_win_rate'    => $setWr,
            ]);
        }
    }
}
