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
        'confirmation_expires_at',
        'confirmation_token',
        'confirmed_at',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'left_at'   => 'datetime',
        'confirmation_expires_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    public const STATUS_ACTIVE     = 'active';
    public const STATUS_PROMOTED   = 'promoted';
    public const STATUS_RELEGATED  = 'relegated';
    public const STATUS_ELIMINATED = 'eliminated';
    public const STATUS_RESERVE    = 'reserve';
    public const STATUS_PENDING_CONFIRMATION = 'pending_confirmation';

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

    public function isPendingConfirmation(): bool
    {
        return $this->status === self::STATUS_PENDING_CONFIRMATION;
    }

    public function offerSpot(int $reservePosition = null): void
    {
        $this->update([
            'status' => self::STATUS_PENDING_CONFIRMATION,
            'reserve_position' => null,
            'confirmation_token' => \Illuminate\Support\Str::random(48),
            'confirmation_expires_at' => now()->addHours(2),
        ]);
    }

    public function confirmSpot(): void
    {
        $this->update([
            'status' => self::STATUS_ACTIVE,
            'joined_at' => now(),
            'left_at' => null,
            'reserve_position' => null,
            'confirmation_token' => null,
            'confirmation_expires_at' => null,
            'confirmed_at' => now(),
        ]);
    }

    public function expireConfirmation(int $newPosition): void
    {
        $this->update([
            'status' => self::STATUS_RESERVE,
            'confirmation_token' => null,
            'confirmation_expires_at' => null,
            'confirmed_at' => null,
            'reserve_position' => $newPosition,
        ]);
    }

}
