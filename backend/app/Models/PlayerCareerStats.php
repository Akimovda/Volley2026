<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerCareerStats extends Model
{
    protected $table = 'player_career_stats';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'direction',
        'total_tournaments',
        'total_matches',
        'total_wins',
        'total_sets_won',
        'total_sets_lost',
        'total_points_scored',
        'total_points_conceded',
        'match_win_rate',
        'set_win_rate',
        'best_placement',
        'elo_rating',
        'updated_at',
    ];

    protected $casts = [
        'match_win_rate' => 'float',
        'set_win_rate'   => 'float',
        'updated_at'     => 'datetime',
    ];

    public const DIRECTION_CLASSIC = 'classic';
    public const DIRECTION_BEACH   = 'beach';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pointDiff(): int
    {
        return $this->total_points_scored - $this->total_points_conceded;
    }

    public function setDiff(): int
    {
        return $this->total_sets_won - $this->total_sets_lost;
    }

    public function recalcRates(): self
    {
        $this->match_win_rate = $this->total_matches > 0
            ? round(($this->total_wins / $this->total_matches) * 100, 2)
            : 0;

        $totalSets = $this->total_sets_won + $this->total_sets_lost;
        $this->set_win_rate = $totalSets > 0
            ? round(($this->total_sets_won / $totalSets) * 100, 2)
            : 0;

        return $this;
    }

}
