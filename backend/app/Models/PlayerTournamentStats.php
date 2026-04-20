<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerTournamentStats extends Model
{
    protected $table = 'player_tournament_stats';

    protected $fillable = [
        'event_id',
        'user_id',
        'team_id',
        'matches_played',
        'matches_won',
        'sets_won',
        'sets_lost',
        'points_scored',
        'points_conceded',
        'match_win_rate',
        'set_win_rate',
        'point_diff',
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
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(EventTeam::class, 'team_id');
    }

    public function recalcRates(): self
    {
        $this->match_win_rate = $this->matches_played > 0
            ? round(($this->matches_won / $this->matches_played) * 100, 2)
            : 0;

        $totalSets = $this->sets_won + $this->sets_lost;
        $this->set_win_rate = $totalSets > 0
            ? round(($this->sets_won / $totalSets) * 100, 2)
            : 0;

        $this->point_diff = $this->points_scored - $this->points_conceded;

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

    /**
     * Есть ли детальная статистика по этому турниру.
     */
    public function hasDetailedStats(): bool
    {
        return $this->total_serves > 0 || $this->total_attacks > 0 || $this->total_digs > 0;
    }

}
