<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KingOfCourtEvent extends Model
{
    protected $fillable = [
        'stage_id',
        'round_number',
        'event_type',
        'team_id',
        'king_team_id',
        'challenger_team_id',
        'queue',
        'round_points',
        'created_by_user_id',
    ];

    protected $casts = [
        'queue'        => 'array',
        'round_points' => 'array',
    ];

    public function stage(): BelongsTo
    {
        return $this->belongsTo(TournamentStage::class, 'stage_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(EventTeam::class, 'team_id');
    }

    public function kingTeam(): BelongsTo
    {
        return $this->belongsTo(EventTeam::class, 'king_team_id');
    }

    public function challengerTeam(): BelongsTo
    {
        return $this->belongsTo(EventTeam::class, 'challenger_team_id');
    }
}
