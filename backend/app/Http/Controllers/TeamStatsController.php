<?php

namespace App\Http\Controllers;

use App\Models\EventTeam;
use App\Models\TournamentMatch;
use App\Models\TournamentStanding;
use App\Models\PlayerTournamentStats;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TeamStatsController extends Controller
{
    /**
     * Публичная страница статистики команды.
     * GET /teams/{team}/stats
     */
    public function show(EventTeam $team)
    {
        $team->load(['captain', 'members.user', 'event']);

        // Standings команды (по всем стадиям)
        $standings = TournamentStanding::where('team_id', $team->id)
            ->with(['stage', 'group'])
            ->orderBy('stage_id')
            ->get();

        // Все матчи команды
        $matches = TournamentMatch::where(function ($q) use ($team) {
                $q->where('team_home_id', $team->id)
                  ->orWhere('team_away_id', $team->id);
            })
            ->whereIn('status', ['completed', 'forfeit'])
            ->with(['teamHome', 'teamAway', 'stage'])
            ->orderByDesc('scored_at')
            ->get();

        // Статистика каждого игрока В СОСТАВЕ этой команды
        $playerStats = PlayerTournamentStats::where('team_id', $team->id)
            ->with('user')
            ->orderByDesc('match_win_rate')
            ->get();

        // Общая статистика команды
        $teamStats = [
            'matches_played' => $matches->count(),
            'wins'           => $matches->where('winner_team_id', $team->id)->count(),
            'losses'         => $matches->count() - $matches->where('winner_team_id', $team->id)->count(),
            'sets_won'       => 0,
            'sets_lost'      => 0,
            'points_scored'  => 0,
            'points_conceded' => 0,
        ];

        foreach ($matches as $m) {
            if ($m->team_home_id === $team->id) {
                $teamStats['sets_won']        += $m->sets_home;
                $teamStats['sets_lost']       += $m->sets_away;
                $teamStats['points_scored']   += $m->total_points_home;
                $teamStats['points_conceded'] += $m->total_points_away;
            } else {
                $teamStats['sets_won']        += $m->sets_away;
                $teamStats['sets_lost']       += $m->sets_home;
                $teamStats['points_scored']   += $m->total_points_away;
                $teamStats['points_conceded'] += $m->total_points_home;
            }
        }

        $teamStats['match_win_rate'] = $teamStats['matches_played'] > 0
            ? round($teamStats['wins'] / $teamStats['matches_played'] * 100, 1)
            : 0;

        return view('teams.stats', compact('team', 'standings', 'matches', 'playerStats', 'teamStats'));
    }

    /**
     * Экспорт результатов турнира в CSV (Excel-совместимый).
     * GET /events/{event}/tournament/excel/results
     */
    public function exportResults(Request $request, \App\Models\Event $event): StreamedResponse
    {
        $stages = $event->tournamentStages()
            ->with(['groups.standings.team', 'matches.teamHome', 'matches.teamAway'])
            ->get();

        $filename = 'tournament_results_' . $event->id . '_' . date('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($stages, $event) {
            $out = fopen('php://output', 'w');

            // BOM для корректного UTF-8 в Excel
            fwrite($out, "\xEF\xBB\xBF");

            // Заголовок
            fputcsv($out, ['Турнир: ' . $event->title], ';');
            fputcsv($out, ['Дата экспорта: ' . date('d.m.Y H:i')], ';');
            fputcsv($out, [], ';');

            foreach ($stages as $stage) {
                fputcsv($out, ['--- Стадия: ' . $stage->name . ' (' . $stage->type . ') ---'], ';');
                fputcsv($out, [], ';');

                // Standings
                foreach ($stage->groups as $group) {
                    fputcsv($out, [$group->name], ';');
                    fputcsv($out, ['Место', 'Команда', 'Матчей', 'Побед', 'Поражений', 'Сеты +', 'Сеты -', 'Очки +', 'Очки -', 'Рейт. очки'], ';');

                    foreach ($group->standings->sortBy('rank') as $s) {
                        fputcsv($out, [
                            $s->rank,
                            $s->team?->name ?? "Team #{$s->team_id}",
                            $s->played,
                            $s->wins,
                            $s->losses,
                            $s->sets_won,
                            $s->sets_lost,
                            $s->points_scored,
                            $s->points_conceded,
                            $s->rating_points,
                        ], ';');
                    }
                    fputcsv($out, [], ';');
                }

                // Матчи
                fputcsv($out, ['Матчи'], ';');
                fputcsv($out, ['#', 'Раунд', 'Хозяева', 'Гости', 'Счёт (сеты)', 'Подробно', 'Статус'], ';');

                foreach ($stage->matches->sortBy(['round', 'match_number']) as $m) {
                    fputcsv($out, [
                        $m->match_number,
                        $m->round,
                        $m->teamHome?->name ?? 'TBD',
                        $m->teamAway?->name ?? 'TBD',
                        $m->setsScore(),
                        $m->detailedScore(),
                        $m->status,
                    ], ';');
                }
                fputcsv($out, [], ';');
            }

            // Рейтинг игроков
            $playerStats = PlayerTournamentStats::where('event_id', $event->id)
                ->with('user')
                ->orderByDesc('match_win_rate')
                ->get();

            if ($playerStats->isNotEmpty()) {
                fputcsv($out, ['--- Рейтинг игроков ---'], ';');
                fputcsv($out, ['Игрок', 'Команда', 'Матчей', 'Побед', 'WinRate %', 'Сеты +', 'Сеты -', 'Очки +', 'Очки -'], ';');

                foreach ($playerStats as $ps) {
                    fputcsv($out, [
                        $ps->user?->name ?? "Игрок #{$ps->user_id}",
                        $ps->team?->name ?? "Team #{$ps->team_id}",
                        $ps->matches_played,
                        $ps->matches_won,
                        $ps->match_win_rate,
                        $ps->sets_won,
                        $ps->sets_lost,
                        $ps->points_scored,
                        $ps->points_conceded,
                    ], ';');
                }
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Экспорт сезонной статистики в CSV.
     * GET /seasons/{season}/excel
     */
    public function exportSeasonStats(\App\Models\TournamentSeason $season): StreamedResponse
    {
        $season->load(['leagues', 'stats.user', 'stats.league']);

        $filename = 'season_stats_' . $season->slug . '_' . date('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($season) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['Сезон: ' . $season->name], ';');
            fputcsv($out, ['Направление: ' . ($season->direction === 'beach' ? 'Пляжный' : 'Классический')], ';');
            fputcsv($out, ['Дата экспорта: ' . date('d.m.Y H:i')], ';');
            fputcsv($out, [], ';');

            foreach ($season->leagues as $league) {
                fputcsv($out, ['--- Лига: ' . $league->name . ' ---'], ';');
                fputcsv($out, ['#', 'Игрок', 'Туров', 'Матчей', 'Побед', 'WinRate %', 'Сеты +', 'Сеты -', 'Очки +', 'Очки -', 'Streak', 'Elo'], ';');

                $leagueStats = $season->stats
                    ->where('league_id', $league->id)
                    ->sortByDesc('match_win_rate')
                    ->values();

                foreach ($leagueStats as $i => $stat) {
                    fputcsv($out, [
                        $i + 1,
                        $stat->user?->name ?? "Игрок #{$stat->user_id}",
                        $stat->rounds_played,
                        $stat->matches_played,
                        $stat->matches_won,
                        $stat->match_win_rate,
                        $stat->sets_won,
                        $stat->sets_lost,
                        $stat->points_scored,
                        $stat->points_conceded,
                        $stat->current_streak,
                        $stat->elo_season,
                    ], ';');
                }
                fputcsv($out, [], ';');
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
