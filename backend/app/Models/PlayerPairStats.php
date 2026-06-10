<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerPairStats extends Model
{
    protected $table = 'player_pair_stats';

    protected $fillable = [
        'player1_id', 'player2_id', 'direction', 'game_scheme',
        'matches_together', 'wins_together',
    ];

    protected $casts = [
        'matches_together' => 'integer',
        'wins_together'    => 'integer',
    ];

    public function player1(): BelongsTo { return $this->belongsTo(User::class, 'player1_id'); }
    public function player2(): BelongsTo { return $this->belongsTo(User::class, 'player2_id'); }

    public function winRate(): float
    {
        return $this->matches_together > 0
            ? round($this->wins_together / $this->matches_together * 100, 1)
            : 0;
    }
}
