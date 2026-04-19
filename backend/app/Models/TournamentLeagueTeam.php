<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentLeagueTeam extends Model
{
    protected $fillable = [
        'league_id',
        'team_id',
        'user_id',
        'status',
        'joined_at',
        'left_at',
        'reserve_position',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'left_at'   => 'datetime',
    ];

    public const STATUS_ACTIVE     = 'active';
    public const STATUS_PROMOTED   = 'promoted';
    public const STATUS_RELEGATED  = 'relegated';
    public const STATUS_ELIMINATED = 'eliminated';
    public const STATUS_RESERVE    = 'reserve';

    /* ---------- relations ---------- */

    public function league(): BelongsTo
    {
        return $this->belongsTo(TournamentLeague::class, 'league_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(EventTeam::class, 'team_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /* ---------- helpers ---------- */

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isReserve(): bool
    {
        return $this->status === self::STATUS_RESERVE;
    }

    public function promote(): void
    {
        $this->update(['status' => self::STATUS_PROMOTED, 'left_at' => now()]);
    }

    public function relegate(): void
    {
        $this->update(['status' => self::STATUS_RELEGATED, 'left_at' => now()]);
    }

    public function eliminate(int $reservePosition): void
    {
        $this->update([
            'status'           => self::STATUS_RESERVE,
            'left_at'          => now(),
            'reserve_position' => $reservePosition,
        ]);
    }

    public function activateFromReserve(): void
    {
        $this->update([
            'status'           => self::STATUS_ACTIVE,
            'joined_at'        => now(),
            'left_at'          => null,
            'reserve_position' => null,
        ]);
    }
}
