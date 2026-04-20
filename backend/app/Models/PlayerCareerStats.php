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
        // detailed stats
        'total_serves', 'total_aces', 'total_serve_errors',
        'total_attacks', 'total_kills', 'total_attack_errors',
        'total_blocks', 'total_block_errors',
        'total_digs', 'total_reception_errors',
        'total_assists', 'total_points_detailed',
        'serve_efficiency', 'attack_efficiency', 'reception_efficiency',
        'mvp_count',
    ];

    protected $casts = [
        'match_win_rate'       => 'float',
        'set_win_rate'         => 'float',
        'serve_efficiency'     => 'float',
        'attack_efficiency'    => 'float',
        'reception_efficiency' => 'float',
        'updated_at'           => 'datetime',
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

        // Detailed efficiency
        $this->total_points_detailed = $this->total_aces + $this->total_kills + $this->total_blocks;

        $this->serve_efficiency = $this->total_serves > 0
            ? round(($this->total_aces / $this->total_serves) * 100, 2) : 0;

        $this->attack_efficiency = $this->total_attacks > 0
            ? round(($this->total_kills / $this->total_attacks) * 100, 2) : 0;

        $this->reception_efficiency = $this->total_digs > 0
            ? round((($this->total_digs - $this->total_reception_errors) / $this->total_digs) * 100, 2) : 0;

        return $this;
    }

}
