<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerCareerStats extends Model
{
    protected $table = 'player_career_stats';

    protected $fillable = [
        'user_id', 'direction',
        'total_tournaments', 'total_matches', 'total_wins',
        'total_sets_won', 'total_sets_lost',
        'total_points_scored', 'total_points_conceded',
        'match_win_rate', 'set_win_rate', 'best_placement', 'elo_rating',
    ];

    protected $casts = [
        'total_tournaments' => 'integer', 'total_matches' => 'integer',
        'total_wins' => 'integer', 'total_sets_won' => 'integer',
        'total_sets_lost' => 'integer', 'total_points_scored' => 'integer',
        'total_points_conceded' => 'integer',
        'match_win_rate' => 'decimal:2', 'set_win_rate' => 'decimal:2',
        'best_placement' => 'integer', 'elo_rating' => 'integer',
    ];

    public const DIRECTION_CLASSIC = 'classic';
    public const DIRECTION_BEACH   = 'beach';

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function recalcRates(): self
    {
        $this->match_win_rate = $this->total_matches > 0
            ? round($this->total_wins / $this->total_matches * 100, 2) : 0;

        $totalSets = $this->total_sets_won + $this->total_sets_lost;
        $this->set_win_rate = $totalSets > 0
            ? round($this->total_sets_won / $totalSets * 100, 2) : 0;

        return $this;
    }

    public function pointDiff(): int
    {
        return $this->total_points_scored - $this->total_points_conceded;
    }

    public function updateBestPlacement(int $placement): self
    {
        if ($this->best_placement === null || $placement < $this->best_placement) {
            $this->best_placement = $placement;
        }
        return $this;
    }
}
