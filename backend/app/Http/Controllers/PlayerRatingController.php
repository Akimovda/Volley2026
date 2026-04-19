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
        $sort      = $request->input('sort', 'match_win_rate');
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
        $sortColumn = match ($sort) {
            'elo_rating'      => 'elo_rating',
            'matches_played'  => 'total_matches',
            default           => 'match_win_rate',
        };

        $query = PlayerCareerStats::where('direction', $direction)
            ->where('total_matches', '>=', 3) // минимум 3 матча
            ->with('user');

        if ($cityId) {
            $query->whereHas('user', fn($q) => $q->where('city_id', $cityId));
        }

        return $query->orderByDesc($sortColumn)
            ->orderByDesc('total_matches')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Рейтинг за сезон (tournament_season_stats).
     */
    protected function seasonRating(int $seasonId, string $sort, ?int $cityId, int $perPage)
    {
        $sortColumn = match ($sort) {
            'elo_rating'     => 'elo_season',
            'matches_played' => 'matches_played',
            default          => 'match_win_rate',
        };

        $query = TournamentSeasonStats::where('season_id', $seasonId)
            ->where('matches_played', '>=', 1)
            ->with(['user', 'league']);

        if ($cityId) {
            $query->whereHas('user', fn($q) => $q->where('city_id', $cityId));
        }

        return $query->orderByDesc($sortColumn)
            ->orderByDesc('matches_played')
            ->paginate($perPage)
            ->withQueryString();
    }
}
