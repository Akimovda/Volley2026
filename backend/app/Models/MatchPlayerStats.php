<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchPlayerStats extends Model
{
    protected $table = 'match_player_stats';

    protected $fillable = [
        'match_id', 'set_number', 'user_id', 'team_id',
        'serves_total', 'aces', 'serve_errors',
        'attacks_total', 'kills', 'attack_errors',
        'blocks', 'block_errors',
        'digs', 'reception_errors',
        'assists', 'points_scored',
    ];

    protected $casts = [
        'set_number' => 'integer',
    ];

    public function match(): BelongsTo
    {
        return $this->belongsTo(TournamentMatch::class, 'match_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(EventTeam::class, 'team_id');
    }

    /**
     * Авто-расчёт points_scored = aces + kills + blocks
     */
    public function calcPoints(): self
    {
        $this->points_scored = $this->aces + $this->kills + $this->blocks;
        return $this;
    }
}
