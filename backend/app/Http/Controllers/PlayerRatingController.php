<?php

namespace App\Http\Controllers;

use App\Models\PlayerCareerStats;
use App\Models\PlayerPairStats;
use App\Models\PlayerRatingHistory;
use App\Models\TournamentSeason;
use App\Models\TournamentSeasonStats;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlayerRatingController extends Controller
{
    /**
     * GET /players/rating — таблица рейтинга игроков.
     */
    public function index(Request $request)
    {
        $direction = $request->input('direction', 'beach');
        $sort      = $request->input('sort', 'rating');
        $dir       = $request->input('dir', 'desc');
        $search    = $request->input('search');
        $seasonId  = $request->input('season_id');
        $perPage   = 30;

        $seasons = TournamentSeason::where('status', '!=', 'draft')
            ->orderByDesc('starts_at')
            ->get(['id', 'name', 'direction', 'status']);

        if ($seasonId) {
            $players = $this->seasonRating($seasonId, $sort, $dir, $search, $perPage);
            $isSeasonMode = true;
        } else {
            $players = $this->careerRating($direction, $sort, $dir, $search, $perPage);
            $isSeasonMode = false;
        }

        // Δ7д для карьерного режима
        if (!$isSeasonMode) {
            $userIds = $players->pluck('user_id')->all();
            $firstHistory = PlayerRatingHistory::whereIn('user_id', $userIds)
                ->where('recorded_at', '>=', now()->subDays(7))
                ->orderBy('recorded_at')
                ->get(['user_id', 'mu_before'])
                ->groupBy('user_id')
                ->map(fn($g) => $g->first());

            foreach ($players as $p) {
                $h = $firstHistory->get($p->user_id);
                $p->delta_7d = $h ? round($p->mu - $h->mu_before, 2) : 0;
            }
        }

        return view('players.rating', compact(
            'players', 'direction', 'sort', 'dir', 'search',
            'seasons', 'seasonId', 'isSeasonMode'
        ));
    }

    /**
     * GET /players/teams — связки и команды.
     */
    public function teams(Request $request)
    {
        $direction = $request->input('direction', 'beach');
        $scheme    = $request->input('scheme');
        $sort      = $request->input('sort', 'winrate');
        $search    = $request->input('search');

        // Классика — игроков больше двух: считаем по командам (одинаковый состав), а не по парам
        if ($direction === 'classic') {
            return $this->teamsClassic($request, $scheme, $sort, $search);
        }

        $query = PlayerPairStats::query()
            ->join('users as u1', 'u1.id', '=', 'player_pair_stats.player1_id')
            ->join('users as u2', 'u2.id', '=', 'player_pair_stats.player2_id')
            ->where('player_pair_stats.direction', $direction)
            ->where('player_pair_stats.matches_together', '>', 0)
            ->selectRaw("player_pair_stats.*,
                u1.first_name as p1_first, u1.last_name as p1_last,
                u2.first_name as p2_first, u2.last_name as p2_last,
                CASE WHEN matches_together > 0
                    THEN ROUND(wins_together::decimal / matches_together * 100, 1)
                    ELSE 0 END as winrate");

        if ($scheme) {
            $query->where('player_pair_stats.game_scheme', $scheme);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('u1.first_name', 'ILIKE', "%$search%")
                  ->orWhere('u1.last_name',  'ILIKE', "%$search%")
                  ->orWhere('u2.first_name', 'ILIKE', "%$search%")
                  ->orWhere('u2.last_name',  'ILIKE', "%$search%");
            });
        }

        match ($sort) {
            'wins'    => $query->orderByDesc('wins_together'),
            'matches' => $query->orderByDesc('matches_together'),
            default   => $query->orderByDesc('winrate')->orderByDesc('matches_together'),
        };

        $pairs = $query->paginate(30)->withQueryString();

        // Аватары игроков пар: один запрос + eager load media (profile_photo_url иначе даёт N+1)
        $userIds = $pairs->getCollection()->pluck('player1_id')
            ->merge($pairs->getCollection()->pluck('player2_id'))->unique()->values();
        $pairUsers = \App\Models\User::whereIn('id', $userIds)->with('media')->get()->keyBy('id');

        $availableSchemes = $direction === 'beach'
            ? ['2x2', '3x3', '4x4']
            : ['4x4', '4x2', '5x1', '5x1_libero'];

        return view('players.teams', compact(
            'pairs', 'pairUsers', 'direction', 'sort', 'scheme', 'availableSchemes', 'search'
        ));
    }


    /**
     * Классика: «команда» = одинаковый набор подтверждённых игроков в разных турнирах.
     * Статистика — по завершённым зачётным матчам (как TournamentMatch::ratable()).
     */
    private function teamsClassic(Request $request, ?string $scheme, string $sort, ?string $search)
    {
        $rosters = DB::table('event_teams as t')
            ->join('event_team_members as m', function ($j) {
                $j->on('m.event_team_id', '=', 't.id')->where('m.confirmation_status', '=', 'confirmed');
            })
            ->where('t.team_kind', 'classic_team')
            ->groupBy('t.id', 't.name')
            ->havingRaw('COUNT(*) >= 2')
            ->selectRaw("t.id as team_id, t.name, string_agg(m.user_id::text, ',' ORDER BY m.user_id) as roster, COUNT(*) as size");

        $matchesBase = fn (string $col, string $won) => DB::table('tournament_matches as tm')
            ->join('tournament_stages as st', 'st.id', '=', 'tm.stage_id')
            ->join('events as ev', 'ev.id', '=', 'st.event_id')
            ->leftJoin('event_tournament_settings as ets', 'ets.event_id', '=', 'ev.id')
            ->where('tm.status', 'completed')
            ->whereNotNull("tm.$col")
            ->whereNotNull('tm.winner_team_id')
            ->whereRaw('(tm.is_tiebreaker IS NULL OR tm.is_tiebreaker = false)')
            ->whereRaw("(st.type <> 'friendly' OR ev.stats_rated = true)")
            ->selectRaw("tm.id as match_id, tm.$col as team_id, ($won) as won, ets.game_scheme as scheme");

        $sides = $matchesBase('team_home_id', 'tm.winner_team_id = tm.team_home_id')
            ->unionAll($matchesBase('team_away_id', 'tm.winner_team_id = tm.team_away_id'));

        $agg = DB::query()
            ->fromSub($rosters, 'r')
            ->joinSub($sides, 'sd', 'sd.team_id', '=', 'r.team_id')
            ->groupBy('r.roster')
            ->selectRaw("r.roster,
                COUNT(*) as matches_together,
                SUM(CASE WHEN sd.won THEN 1 ELSE 0 END) as wins_together,
                MAX(r.size) as size,
                (array_agg(r.team_id ORDER BY sd.match_id DESC))[1] as last_team_id,
                (array_agg(r.name ORDER BY sd.match_id DESC))[1] as team_name,
                (array_agg(sd.scheme ORDER BY sd.match_id DESC))[1] as game_scheme");

        $query = DB::query()->fromSub($agg, 'x')
            ->selectRaw("x.*, ROUND(wins_together::decimal / matches_together * 100, 1) as winrate");

        if ($scheme) {
            $query->where('x.game_scheme', $scheme);
        }
        if ($search) {
            $like = '%' . $search . '%';
            $query->where(function ($q) use ($like) {
                $q->where('x.team_name', 'ILIKE', $like)
                  ->orWhereRaw("EXISTS (SELECT 1 FROM users u
                        WHERE u.id = ANY(string_to_array(x.roster, ',')::bigint[])
                          AND (u.first_name ILIKE ? OR u.last_name ILIKE ?))", [$like, $like]);
            });
        }

        match ($sort) {
            'wins'    => $query->orderByDesc('wins_together'),
            'matches' => $query->orderByDesc('matches_together'),
            default   => $query->orderByDesc('winrate')->orderByDesc('matches_together'),
        };

        $pairs = $query->paginate(30)->withQueryString();

        // Состав последней версии команды — для всплывающего окна
        $teamIds = $pairs->getCollection()->pluck('last_team_id')->all();
        $members = \App\Models\EventTeamMember::query()
            ->whereIn('event_team_id', $teamIds)
            ->where('confirmation_status', 'confirmed')
            ->with('user.media')
            ->orderBy('position_order')->orderBy('id')
            ->get()->groupBy('event_team_id');

        $positionNames = __('events.positions');
        $posCodes = ['outside', 'opposite', 'middle', 'setter', 'libero', 'reserve'];
        $teamRosters = [];
        foreach ($teamIds as $tid) {
            $list = [];
            foreach ($members->get($tid, collect()) as $mem) {
                $code = $mem->position_code ?: (in_array($mem->role_code, $posCodes, true) ? $mem->role_code : null);
                $u = $mem->user;
                $list[] = [
                    'id'       => $mem->user_id,
                    'name'     => $u ? (trim(($u->last_name ?? '') . ' ' . ($u->first_name ?? '')) ?: '#' . $mem->user_id) : '#' . $mem->user_id,
                    'avatar'   => $u?->profile_photo_url,
                    'url'      => route('users.show', $mem->user_id),
                    'position' => $code ? ($positionNames[$code] ?? null) : null,
                    'captain'  => $mem->role_code === 'captain',
                ];
            }
            $teamRosters[$tid] = $list;
        }

        $direction = 'classic';
        $pairUsers = collect();
        $availableSchemes = ['4x4', '4x2', '5x1', '5x1_libero'];

        return view('players.teams', compact(
            'pairs', 'pairUsers', 'teamRosters', 'direction', 'sort', 'scheme', 'availableSchemes', 'search'
        ));
    }

    // ---------------------------------------------------------------

    private function careerRating(string $direction, string $sort, string $dir, ?string $search, int $perPage)
    {
        $query = PlayerCareerStats::query()
            ->join('users', 'users.id', '=', 'player_career_stats.user_id')
            ->where('player_career_stats.direction', $direction)
            ->where('player_career_stats.total_matches', '>', 0)
            ->selectRaw('player_career_stats.*, users.first_name, users.last_name, users.city_id,
                (mu - 3 * sigma) as conservative_rating');

        if ($search) {
            $query->where(fn($q) => $q
                ->where('users.first_name', 'ILIKE', "%$search%")
                ->orWhere('users.last_name',  'ILIKE', "%$search%"));
        }

        match ($sort) {
            'mu'      => $query->orderBy('mu', $dir),
            'wins'    => $query->orderBy('total_wins', $dir),
            'games'   => $query->orderBy('total_matches', $dir),
            'meetings'=> $query->orderBy('unique_opponents', $dir),
            'delta7'  => $query->orderByRaw('(mu - 3 * sigma) ' . $dir), // заполнится JS-сортировкой после
            default   => $query->orderByRaw('(mu - 3 * sigma) ' . $dir),
        };

        return $query->paginate($perPage)->withQueryString();
    }

    private function seasonRating(int $seasonId, string $sort, string $dir, ?string $search, int $perPage)
    {
        $query = TournamentSeasonStats::where('season_id', $seasonId)
            ->where('matches_played', '>=', 1)
            ->with(['user', 'league']);

        if ($search) {
            $query->whereHas('user', fn($q) => $q
                ->where('first_name', 'ILIKE', "%$search%")
                ->orWhere('last_name',  'ILIKE', "%$search%"));
        }

        match ($sort) {
            'games'   => $query->orderBy('matches_played', $dir),
            'wins'    => $query->orderBy('matches_won', $dir),
            'elo'     => $query->orderBy('elo_season', $dir),
            default   => $query->orderByRaw("(mu_season - 3 * sigma_season) $dir"),
        };

        return $query->paginate($perPage)->withQueryString();
    }
}
