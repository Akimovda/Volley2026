<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class TournamentSeason extends Model
{
    protected $fillable = [
        'organizer_id',
        'league_id',
        'name',
        'slug',
        'direction',
        'starts_at',
        'ends_at',
        'status',
        'config',
    ];

    protected $casts = [
        'config'    => 'array',
        'starts_at' => 'date',
        'ends_at'   => 'date',
    ];

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_ACTIVE    = 'active';
    public const STATUS_COMPLETED = 'completed';

    public const DIRECTION_CLASSIC = 'classic';
    public const DIRECTION_BEACH   = 'beach';

    /* ---------- relations ---------- */

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class, 'league_id');
    }

    public function leagues(): HasMany
    {
        return $this->hasMany(TournamentLeague::class, 'season_id')->orderBy('sort_order');
    }

    public function seasonEvents(): HasMany
    {
        return $this->hasMany(TournamentSeasonEvent::class, 'season_id')->orderBy('round_number');
    }

    public function stats(): HasMany
    {
        return $this->hasMany(TournamentSeasonStats::class, 'season_id');
    }

    /* ---------- helpers ---------- */

    public function cfg(string $key, mixed $default = null): mixed
    {
        return data_get($this->config, $key, $default);
    }

    public function isAutoPromotion(): bool
    {
        return (bool) $this->cfg('auto_promotion', false);
    }

    public function promotionRules(): array
    {
        return $this->cfg('promotion_rules', []);
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function currentRound(): int
    {
        return $this->seasonEvents()->max('round_number') ?? 0;
    }
}
