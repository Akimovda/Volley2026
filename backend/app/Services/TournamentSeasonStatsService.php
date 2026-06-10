<?php

namespace App\Services;

use App\Models\Event;
use App\Models\TournamentMatch;
use App\Models\TournamentSeasonStats;
use App\Models\TournamentSeason;
use App\Models\TournamentLeague;
use App\Services\TournamentEloService;
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

        // Обновляем elo_season (был мёртвым — теперь считается)
        app(TournamentEloService::class)->processSeasonMatch($match, $season->id, $league->id);
    }

    private function updateTeamPlayersSeasonStats(TournamentSeason $season, TournamentLeague $league, array $side): void
    {
        $memberUserIds = DB::table('event_team_members')
            ->where('event_team_id', $side['team_id'])
            ->where('confirmation_status', 'confirmed')
            ->pluck('user_id');

        $eventIds = $season->seasonEvents()->pluck('event_id')->all();

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

            $stat->match_win_rate = $stat->matches_played > 0
                ? round($stat->matches_won / $stat->matches_played * 100, 2)
                : 0;

            $totalSets = $stat->sets_won + $stat->sets_lost;
            $stat->set_win_rate = $totalSets > 0
                ? round($stat->sets_won / $totalSets * 100, 2)
                : 0;

            $stat->rounds_played = $this->recalculateRoundsPlayed($stat, $eventIds);

            $stat->save();
        }
    }

    private function recalculateRoundsPlayed(TournamentSeasonStats $stat, array $eventIds): int
    {
        if (empty($eventIds)) return 0;

        $teamIds = DB::table('event_team_members')
            ->where('user_id', $stat->user_id)
            ->pluck('event_team_id');

        if ($teamIds->isEmpty()) return 0;

        return (int) TournamentMatch::where('tournament_matches.status', 'completed')
            ->join('tournament_stages', 'tournament_matches.stage_id', '=', 'tournament_stages.id')
            ->whereIn('tournament_stages.event_id', $eventIds)
            ->whereNotNull('tournament_stages.occurrence_id')
            ->where(function ($q) use ($teamIds) {
                $q->whereIn('tournament_matches.team_home_id', $teamIds)
                  ->orWhereIn('tournament_matches.team_away_id', $teamIds);
            })
            ->count(DB::raw('DISTINCT tournament_stages.occurrence_id'));
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
        $eventIds = $season->seasonEvents()->pluck('event_id')->unique()->values();

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

        // Пересчёт elo_season по всем матчам
        foreach ($eventIds as $eventId) {
            $event = Event::find($eventId);
            if (!$event) continue;

            $league = $season->leagues()->first();
            if (!$league) continue;

            $matches = TournamentMatch::whereHas('stage', fn($q) => $q->where('event_id', $eventId))
                ->where('status', 'completed')
                ->whereNotNull('winner_team_id')
                ->orderBy(DB::raw('COALESCE(scored_at, created_at)'))
                ->get();

            foreach ($matches as $match) {
                app(TournamentEloService::class)->processSeasonMatch($match, $season->id, $league->id);
            }
        }

        // Подсчёт rounds_played: кол-во уникальных occurrence, в которых игрок участвовал
        $eventIdsArr = $eventIds->all();
        TournamentSeasonStats::where('season_id', $season->id)
            ->each(function ($stat) use ($eventIdsArr) {
                $stat->update(['rounds_played' => $this->recalculateRoundsPlayed($stat, $eventIdsArr)]);
            });
    }
}
