<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerTournamentStats extends Model
{
    protected $table = 'player_tournament_stats';

    protected $fillable = [
        'event_id', 'user_id', 'team_id',
        'matches_played', 'matches_won', 'sets_won', 'sets_lost',
        'points_scored', 'points_conceded',
        'match_win_rate', 'set_win_rate', 'point_diff',
    ];

    protected $casts = [
        'matches_played' => 'integer', 'matches_won' => 'integer',
        'sets_won' => 'integer', 'sets_lost' => 'integer',
        'points_scored' => 'integer', 'points_conceded' => 'integer',
        'match_win_rate' => 'decimal:2', 'set_win_rate' => 'decimal:2',
        'point_diff' => 'integer',
    ];

    public function event(): BelongsTo { return $this->belongsTo(Event::class); }
    public function user(): BelongsTo  { return $this->belongsTo(User::class); }
    public function team(): BelongsTo  { return $this->belongsTo(EventTeam::class, 'team_id'); }

    public function recalcRates(): self
    {
        $this->match_win_rate = $this->matches_played > 0
            ? round($this->matches_won / $this->matches_played * 100, 2) : 0;

        $totalSets = $this->sets_won + $this->sets_lost;
        $this->set_win_rate = $totalSets > 0
            ? round($this->sets_won / $totalSets * 100, 2) : 0;

        $this->point_diff = $this->points_scored - $this->points_conceded;
        return $this;
    }
}
