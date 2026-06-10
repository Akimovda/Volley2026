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

        $availableSchemes = $direction === 'beach'
            ? ['2x2', '3x3', '4x4']
            : ['4x4', '4x2', '5x1', '5x1_libero'];

        return view('players.teams', compact(
            'pairs', 'direction', 'sort', 'scheme', 'availableSchemes', 'search'
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
