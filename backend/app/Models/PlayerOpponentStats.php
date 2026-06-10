<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerOpponentStats extends Model
{
    protected $table = 'player_opponent_stats';

    protected $fillable = [
        'user_id', 'opponent_id',
        'matches_against', 'wins_against',
    ];

    protected $casts = [
        'matches_against' => 'integer',
        'wins_against'    => 'integer',
    ];

    public function user(): BelongsTo     { return $this->belongsTo(User::class); }
    public function opponent(): BelongsTo { return $this->belongsTo(User::class, 'opponent_id'); }

    public function winRate(): float
    {
        return $this->matches_against > 0
            ? round($this->wins_against / $this->matches_against * 100, 1)
            : 0;
    }
}
