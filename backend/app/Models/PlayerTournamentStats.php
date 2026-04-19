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
    ];

    protected $casts = [
        'match_win_rate' => 'float',
        'set_win_rate'   => 'float',
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
}
