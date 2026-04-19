<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentSeasonStats extends Model
{
    protected $table = 'tournament_season_stats';

    protected $fillable = [
        'season_id',
        'league_id',
        'user_id',
        'rounds_played',
        'matches_played',
        'matches_won',
        'sets_won',
        'sets_lost',
        'points_scored',
        'points_conceded',
        'match_win_rate',
        'set_win_rate',
        'best_placement',
        'current_streak',
        'elo_season',
    ];

    protected $casts = [
        'match_win_rate' => 'float',
        'set_win_rate'   => 'float',
    ];

    /* ---------- relations ---------- */

    public function season(): BelongsTo
    {
        return $this->belongsTo(TournamentSeason::class, 'season_id');
    }

    public function league(): BelongsTo
    {
        return $this->belongsTo(TournamentLeague::class, 'league_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /* ---------- computed ---------- */

    public function pointDiff(): int
    {
        return $this->points_scored - $this->points_conceded;
    }

    public function setDiff(): int
    {
        return $this->sets_won - $this->sets_lost;
    }
}
