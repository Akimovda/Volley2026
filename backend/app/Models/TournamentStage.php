<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TournamentStage extends Model
{
    protected $fillable = [
        'event_id', 'type', 'name', 'sort_order', 'config', 'status',
    ];

    protected $casts = [
        'config'     => 'array',
        'sort_order' => 'integer',
    ];

    public const TYPE_ROUND_ROBIN    = 'round_robin';
    public const TYPE_GROUPS_PLAYOFF = 'groups_playoff';
    public const TYPE_SINGLE_ELIM    = 'single_elim';
    public const TYPE_DOUBLE_ELIM    = 'double_elim';
    public const TYPE_SWISS          = 'swiss';
    public const TYPE_KING_OF_COURT  = 'king_of_court';
    public const TYPE_THAI           = 'thai';

    public const TYPES = [
        self::TYPE_ROUND_ROBIN, self::TYPE_GROUPS_PLAYOFF,
        self::TYPE_SINGLE_ELIM, self::TYPE_DOUBLE_ELIM,
        self::TYPE_SWISS, self::TYPE_KING_OF_COURT, self::TYPE_THAI,
    ];

    public const STATUS_PENDING     = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED   = 'completed';

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(TournamentGroup::class, 'stage_id')->orderBy('sort_order');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(TournamentMatch::class, 'stage_id');
    }

    public function standings(): HasMany
    {
        return $this->hasMany(TournamentStanding::class, 'stage_id');
    }

    /** Получить значение из config JSON. */
    public function cfg(string $key, mixed $default = null): mixed
    {
        return data_get($this->config, $key, $default);
    }

    public function matchFormat(): string
    {
        return $this->cfg('match_format', 'bo3');
    }

    public function setPoints(): int
    {
        return (int) $this->cfg('set_points', 25);
    }

    public function decidingSetPoints(): int
    {
        return (int) $this->cfg('deciding_set_points', 15);
    }

    public function isPending(): bool    { return $this->status === self::STATUS_PENDING; }
    public function isInProgress(): bool { return $this->status === self::STATUS_IN_PROGRESS; }
    public function isCompleted(): bool  { return $this->status === self::STATUS_COMPLETED; }
}
