<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\TournamentSeason;

class OrgTournamentAnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $orgId = $request->user()->id;

        // --- ОБЩИЕ МЕТРИКИ ---
        $totalTournaments = DB::table('events')
            ->where('organizer_id', $orgId)
            ->where('format', 'tournament')
            ->count();

        $totalMatches = DB::table('tournament_matches as tm')
            ->join('tournament_stages as ts', 'ts.id', '=', 'tm.stage_id')
            ->join('events as e', 'e.id', '=', 'ts.event_id')
            ->where('e.organizer_id', $orgId)
            ->where('tm.status', 'completed')
            ->count();

        $uniquePlayers = DB::table('event_team_members as etm')
            ->join('event_teams as et', 'et.id', '=', 'etm.event_team_id')
            ->join('events as e', 'e.id', '=', 'et.event_id')
            ->where('e.organizer_id', $orgId)
            ->where('e.format', 'tournament')
            ->distinct('etm.user_id')
            ->count('etm.user_id');

        $totalTeams = DB::table('event_teams as et')
            ->join('events as e', 'e.id', '=', 'et.event_id')
            ->where('e.organizer_id', $orgId)
            ->where('e.format', 'tournament')
            ->where('et.status', 'submitted')
            ->count();

        // --- СРЕДНЯЯ ЗАПОЛНЯЕМОСТЬ ---
        $avgFillRate = DB::table('events as e')
            ->join('event_tournament_settings as ets', 'ets.event_id', '=', 'e.id')
            ->where('e.organizer_id', $orgId)
            ->where('e.format', 'tournament')
            ->whereNotNull('ets.teams_count')
            ->where('ets.teams_count', '>', 0)
            ->select(DB::raw('
                AVG(
                    (SELECT COUNT(*) FROM event_teams et2
                     WHERE et2.event_id = e.id AND et2.status = \'submitted\')::float
                    / ets.teams_count * 100
                ) as avg_pct
            '))
            ->value('avg_pct');

        // --- ДОХОД С ТУРНИРОВ ---
        $revenue = DB::table('events as e')
            ->leftJoin('event_teams as et', function ($j) {
                $j->on('et.event_id', '=', 'e.id')
                  ->where('et.status', '=', 'submitted');
            })
            ->where('e.organizer_id', $orgId)
            ->where('e.format', 'tournament')
            ->whereNotNull('e.price_minor')
            ->where('e.price_minor', '>', 0)
            ->select(DB::raw('SUM(e.price_minor * (SELECT COUNT(*) FROM event_teams et3 WHERE et3.event_id = e.id AND et3.status = \'submitted\')) as total'))
            ->value('total');

        // --- УЧАСТНИКИ ПО МЕСЯЦАМ ---
        $monthlyParticipants = DB::table('event_team_members as etm')
            ->join('event_teams as et', 'et.id', '=', 'etm.event_team_id')
            ->join('events as e', 'e.id', '=', 'et.event_id')
            ->where('e.organizer_id', $orgId)
            ->where('e.format', 'tournament')
            ->where('etm.created_at', '>=', now()->subMonths(12))
            ->select(
                DB::raw("TO_CHAR(etm.created_at, 'YYYY-MM') as month"),
                DB::raw('COUNT(DISTINCT etm.user_id) as players'),
                DB::raw('COUNT(DISTINCT et.id) as teams')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // --- RETENTION: % вернувшихся на след. турнир ---
        $retentionData = $this->calculateRetention($orgId);

        // --- ТОП ИГРОКОВ ПО WINRATE ---
        $topPlayers = DB::table('player_tournament_stats as pts')
            ->join('events as e', 'e.id', '=', 'pts.event_id')
            ->join('users as u', 'u.id', '=', 'pts.user_id')
            ->where('e.organizer_id', $orgId)
            ->where('u.is_bot', false)
            ->select(
                'u.id', 'u.first_name', 'u.last_name',
                DB::raw('SUM(pts.matches_played) as total_matches'),
                DB::raw('SUM(pts.matches_won) as total_wins'),
                DB::raw('CASE WHEN SUM(pts.matches_played) > 0 THEN ROUND(SUM(pts.matches_won)::numeric / SUM(pts.matches_played) * 100, 1) ELSE 0 END as win_rate'),
                DB::raw('COUNT(DISTINCT pts.event_id) as tournaments')
            )
            ->groupBy('u.id', 'u.first_name', 'u.last_name')
            ->having(DB::raw('SUM(pts.matches_played)'), '>=', 3)
            ->orderByDesc('win_rate')
            ->limit(15)
            ->get();

        // --- СПИСОК ТУРНИРОВ ---
        $tournaments = DB::table('events as e')
            ->leftJoin('event_tournament_settings as ets', 'ets.event_id', '=', 'e.id')
            ->where('e.organizer_id', $orgId)
            ->where('e.format', 'tournament')
            ->select(
                'e.id', 'e.title', 'e.created_at',
                'e.direction', 'ets.game_scheme',
                DB::raw('(SELECT COUNT(*) FROM event_teams et2 WHERE et2.event_id = e.id AND et2.status = \'submitted\') as teams_count'),
                DB::raw('(SELECT COUNT(*) FROM tournament_matches tm JOIN tournament_stages ts ON ts.id = tm.stage_id WHERE ts.event_id = e.id AND tm.status = \'completed\') as matches_played')
            )
            ->orderByDesc('e.created_at')
            ->limit(20)
            ->get();

        // --- СЕЗОНЫ ---
        $seasons = TournamentSeason::where('organizer_id', $orgId)
            ->withCount('leagues')
            ->with(['seasonEvents' => fn($q) => $q->where('status', 'completed')])
            ->orderByDesc('created_at')
            ->get();

        return view('dashboard.org-tournament', compact(
            'totalTournaments', 'totalMatches', 'uniquePlayers', 'totalTeams',
            'avgFillRate', 'revenue',
            'monthlyParticipants', 'retentionData',
            'topPlayers', 'tournaments', 'seasons'
        ));
    }

    /**
     * Retention: % игроков, участвовавших в 2+ турнирах.
     */
    protected function calculateRetention(int $orgId): array
    {
        $playerTournaments = DB::table('event_team_members as etm')
            ->join('event_teams as et', 'et.id', '=', 'etm.event_team_id')
            ->join('events as e', 'e.id', '=', 'et.event_id')
            ->where('e.organizer_id', $orgId)
            ->where('e.format', 'tournament')
            ->select('etm.user_id', DB::raw('COUNT(DISTINCT e.id) as tournament_count'))
            ->groupBy('etm.user_id')
            ->get();

        $total = $playerTournaments->count();
        if ($total === 0) {
            return ['total' => 0, 'returning' => 0, 'rate' => 0];
        }

        $returning = $playerTournaments->where('tournament_count', '>=', 2)->count();

        return [
            'total'     => $total,
            'returning' => $returning,
            'rate'      => round($returning / $total * 100, 1),
        ];
    }
}
