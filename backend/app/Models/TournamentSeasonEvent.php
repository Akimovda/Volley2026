<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentSeasonEvent extends Model
{
    protected $fillable = [
        'season_id',
        'league_id',
        'event_id',
        'occurrence_id',
        'round_number',
        'status',
    ];

    public const STATUS_PENDING   = 'pending';
    public const STATUS_COMPLETED = 'completed';

    /* ---------- relations ---------- */

    public function season(): BelongsTo
    {
        return $this->belongsTo(TournamentSeason::class, 'season_id');
    }

    public function league(): BelongsTo
    {
        return $this->belongsTo(TournamentLeague::class, 'league_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /* ---------- helpers ---------- */

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }
}
