<?php

namespace App\Services;

use App\Models\Event;
use App\Models\TournamentMatch;
use App\Models\TournamentSeasonStats;
use App\Models\TournamentSeason;
use App\Models\TournamentLeague;
use Illuminate\Support\Facades\DB;

class TournamentSeasonStatsService
{
    /**
     * Обновить сезонную статистику после завершённого матча.
     */
    public function updateForMatch(TournamentMatch $match, Event $event): void
    {
        if (!$match->isCompleted() || !$match->winner_team_id || !$event->season_id) {
            return;
        }

        $season = $event->season;
        if (!$season) return;

        $league = $season->leagues()->first();
        if (!$league) return;

        // Обновляем для обеих сторон
        foreach ([
            ['team_id' => $match->team_home_id, 'won' => $match->sets_home, 'lost' => $match->sets_away,
             'scored' => $match->total_points_home, 'conceded' => $match->total_points_away,
             'isWinner' => $match->winner_team_id === $match->team_home_id],
            ['team_id' => $match->team_away_id, 'won' => $match->sets_away, 'lost' => $match->sets_home,
             'scored' => $match->total_points_away, 'conceded' => $match->total_points_home,
             'isWinner' => $match->winner_team_id === $match->team_away_id],
        ] as $side) {
            $this->updateTeamPlayersSeasonStats($season, $league, $side);
        }
    }

    private function updateTeamPlayersSeasonStats(TournamentSeason $season, TournamentLeague $league, array $side): void
    {
        $memberUserIds = DB::table('event_team_members')
            ->where('event_team_id', $side['team_id'])
            ->where('confirmation_status', 'confirmed')
            ->pluck('user_id');

        foreach ($memberUserIds as $userId) {
            $stat = TournamentSeasonStats::firstOrCreate([
                'season_id' => $season->id,
                'league_id' => $league->id,
                'user_id'   => $userId,
            ], [
                'rounds_played'  => 0,
                'matches_played' => 0,
                'matches_won'    => 0,
                'sets_won'       => 0,
                'sets_lost'      => 0,
                'points_scored'  => 0,
                'points_conceded'=> 0,
                'match_win_rate' => 0,
                'set_win_rate'   => 0,
                'best_placement' => null,
                'current_streak' => 0,
                'elo_season'     => 1500,
            ]);

            $stat->matches_played++;
            if ($side['isWinner']) {
                $stat->matches_won++;
                $stat->current_streak = max(0, $stat->current_streak) + 1;
            } else {
                $stat->current_streak = min(0, $stat->current_streak) - 1;
            }
            $stat->sets_won        += $side['won'];
            $stat->sets_lost       += $side['lost'];
            $stat->points_scored   += $side['scored'];
            $stat->points_conceded += $side['conceded'];

            // Recalc rates
            $stat->match_win_rate = $stat->matches_played > 0
                ? round($stat->matches_won / $stat->matches_played * 100, 2)
                : 0;

            $totalSets = $stat->sets_won + $stat->sets_lost;
            $stat->set_win_rate = $totalSets > 0
                ? round($stat->sets_won / $totalSets * 100, 2)
                : 0;

            $stat->save();
        }
    }

    /**
     * Откатить сезонную статистику для матча.
     */
    public function revertMatch(TournamentMatch $match, Event $event): void
    {
        if (!$match->isCompleted() || !$match->winner_team_id || !$event->season_id) {
            return;
        }

        $season = $event->season;
        if (!$season) return;

        $league = $season->leagues()->first();
        if (!$league) return;

        foreach ([
            ['team_id' => $match->team_home_id, 'won' => $match->sets_home, 'lost' => $match->sets_away,
             'scored' => $match->total_points_home, 'conceded' => $match->total_points_away,
             'isWinner' => $match->winner_team_id === $match->team_home_id],
            ['team_id' => $match->team_away_id, 'won' => $match->sets_away, 'lost' => $match->sets_home,
             'scored' => $match->total_points_away, 'conceded' => $match->total_points_home,
             'isWinner' => $match->winner_team_id === $match->team_away_id],
        ] as $side) {
            $this->revertTeamPlayersSeasonStats($season, $league, $side);
        }
    }

    private function revertTeamPlayersSeasonStats(TournamentSeason $season, TournamentLeague $league, array $side): void
    {
        $memberUserIds = DB::table('event_team_members')
            ->where('event_team_id', $side['team_id'])
            ->where('confirmation_status', 'confirmed')
            ->pluck('user_id');

        foreach ($memberUserIds as $userId) {
            $stat = TournamentSeasonStats::where('season_id', $season->id)
                ->where('league_id', $league->id)
                ->where('user_id', $userId)
                ->first();

            if (!$stat) continue;

            $stat->matches_played = max(0, $stat->matches_played - 1);
            if ($side['isWinner']) {
                $stat->matches_won = max(0, $stat->matches_won - 1);
            }
            $stat->sets_won        = max(0, $stat->sets_won - $side['won']);
            $stat->sets_lost       = max(0, $stat->sets_lost - $side['lost']);
            $stat->points_scored   = max(0, $stat->points_scored - $side['scored']);
            $stat->points_conceded = max(0, $stat->points_conceded - $side['conceded']);

            $stat->match_win_rate = $stat->matches_played > 0
                ? round($stat->matches_won / $stat->matches_played * 100, 2)
                : 0;

            $totalSets = $stat->sets_won + $stat->sets_lost;
            $stat->set_win_rate = $totalSets > 0
                ? round($stat->sets_won / $totalSets * 100, 2)
                : 0;

            $stat->save();
        }
    }

    /**
     * Полный пересчёт сезонной статистики с нуля.
     */
    public function rebuildForSeason(TournamentSeason $season): void
    {
        // Очищаем
        TournamentSeasonStats::where('season_id', $season->id)->delete();

        // Находим все events сезона
        $eventIds = $season->seasonEvents()->pluck('event_id');

        foreach ($eventIds as $eventId) {
            $event = Event::find($eventId);
            if (!$event) continue;

            $matches = TournamentMatch::whereHas('stage', fn($q) => $q->where('event_id', $eventId))
                ->where('status', 'completed')
                ->get();

            foreach ($matches as $match) {
                $this->updateForMatch($match, $event);
            }
        }
    }
}
