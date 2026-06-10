<?php

namespace App\Http\Controllers;

use App\Models\PlayerCareerStats;
use App\Models\PlayerTournamentStats;
use App\Models\TournamentSeason;
use App\Models\TournamentSeasonStats;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlayerRatingController extends Controller
{
    /**
     * Публичный рейтинг игроков.
     *
     * GET /players/rating
     *   ?direction=classic|beach
     *   ?season_id=5
     *   ?city=403
     *   ?sort=match_win_rate|elo_rating|matches_played
     */
    public function index(Request $request)
    {
        $direction = $request->input('direction', 'classic');
        $seasonId  = $request->input('season_id');
        $cityId    = $request->input('city');
        $sort      = $request->input('sort', 'conservative_rating');
        $perPage   = 30;

        // Доступные сезоны для фильтра
        $seasons = TournamentSeason::where('status', '!=', 'draft')
            ->orderByDesc('starts_at')
            ->get(['id', 'name', 'direction', 'status']);

        // Режим: по сезону или по карьере
        if ($seasonId) {
            $data = $this->seasonRating($seasonId, $sort, $cityId, $perPage);
        } else {
            $data = $this->careerRating($direction, $sort, $cityId, $perPage);
        }

        return view('players.rating', [
            'players'   => $data,
            'direction' => $direction,
            'seasonId'  => $seasonId,
            'cityId'    => $cityId,
            'sort'      => $sort,
            'seasons'   => $seasons,
        ]);
    }

    /**
     * Карьерный рейтинг (player_career_stats).
     */
    protected function careerRating(string $direction, string $sort, ?int $cityId, int $perPage)
    {
        $query = PlayerCareerStats::where('direction', $direction)
            ->where('total_matches', '>=', 3)
            ->with('user');

        if ($cityId) {
            $query->whereHas('user', fn($q) => $q->where('city_id', $cityId));
        }

        $query = match ($sort) {
            'elo_rating'          => $query->orderByDesc('elo_rating'),
            'matches_played'      => $query->orderByDesc('total_matches'),
            'match_win_rate'      => $query->orderByDesc('match_win_rate'),
            default               => $query->orderByDesc(\Illuminate\Support\Facades\DB::raw('mu - 3 * sigma')),
        };

        return $query->orderByDesc('total_matches')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Рейтинг за сезон (tournament_season_stats).
     */
    protected function seasonRating(int $seasonId, string $sort, ?int $cityId, int $perPage)
    {
        $query = TournamentSeasonStats::where('season_id', $seasonId)
            ->where('matches_played', '>=', 1)
            ->with(['user', 'league']);

        if ($cityId) {
            $query->whereHas('user', fn($q) => $q->where('city_id', $cityId));
        }

        $query = match ($sort) {
            'elo_rating'     => $query->orderByDesc('elo_season'),
            'matches_played' => $query->orderByDesc('matches_played'),
            'match_win_rate' => $query->orderByDesc('match_win_rate'),
            default          => $query->orderByDesc(\Illuminate\Support\Facades\DB::raw('mu_season - 3 * sigma_season')),
        };

        return $query->orderByDesc('matches_played')
            ->paginate($perPage)
            ->withQueryString();
    }
}
