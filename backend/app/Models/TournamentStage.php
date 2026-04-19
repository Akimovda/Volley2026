<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TournamentStage extends Model
{
    protected $fillable = [
        'event_id',
        'type',
        'name',
        'sort_order',
        'config',
        'status',
    ];

    protected $casts = [
        'config' => 'array',
    ];

    /* ---------- constants: stage types ---------- */

    public const TYPE_ROUND_ROBIN    = 'round_robin';
    public const TYPE_GROUPS_PLAYOFF = 'groups_playoff';
    public const TYPE_SINGLE_ELIM    = 'single_elim';
    public const TYPE_DOUBLE_ELIM    = 'double_elim';
    public const TYPE_SWISS          = 'swiss';
    public const TYPE_KING_OF_COURT  = 'king_of_court';
    public const TYPE_THAI           = 'thai';

    public const STATUS_PENDING     = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED   = 'completed';

    // Режимы жеребьёвки
    public const DRAW_SEEDED        = 'seeded';
    public const DRAW_STAGE_ADVANCE = 'stage_advance';
    public const DRAW_LEAGUE_CARRY  = 'league_carry';
    public const DRAW_MANUAL        = 'manual';

    public const DRAW_MODES = [
        self::DRAW_SEEDED,
        self::DRAW_STAGE_ADVANCE,
        self::DRAW_LEAGUE_CARRY,
        self::DRAW_MANUAL,
    ];

    public const SEED_BY_ELO            = 'elo';
    public const SEED_BY_MATCH_WIN_RATE = 'match_win_rate';
    public const SEED_BY_RATING_POINTS  = 'rating_points';

    public const SEED_BY_OPTIONS = [
        self::SEED_BY_ELO,
        self::SEED_BY_MATCH_WIN_RATE,
        self::SEED_BY_RATING_POINTS,
    ];

    public const TYPES = [
        self::TYPE_ROUND_ROBIN,
        self::TYPE_GROUPS_PLAYOFF,
        self::TYPE_SINGLE_ELIM,
        self::TYPE_DOUBLE_ELIM,
        self::TYPE_SWISS,
        self::TYPE_KING_OF_COURT,
        self::TYPE_THAI,
    ];

    /* ---------- relations ---------- */

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

    /* ---------- helpers ---------- */

    /**
     * Алиас для configValue (используется в контроллере).
     */
    public function cfg(string $key, mixed $default = null): mixed
    {
        return $this->configValue($key, $default);
    }

    public function configValue(string $key, mixed $default = null): mixed
    {
        return data_get($this->config, $key, $default);
    }

    public function matchFormat(): string
    {
        return $this->configValue('match_format', 'bo3');
    }

    public function setPoints(): int
    {
        return (int) $this->configValue('set_points', 25);
    }

    public function decidingSetPoints(): int
    {
        return (int) $this->configValue('deciding_set_points', 15);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function drawMode(): string
    {
        return $this->configValue('draw_mode', self::DRAW_SEEDED);
    }

    public function drawSeedBy(): string
    {
        return $this->configValue('draw_seed_by', self::SEED_BY_ELO);
    }

    public function drawModeLabel(): string
    {
        return match ($this->drawMode()) {
            self::DRAW_SEEDED        => 'Посев по рейтингу',
            self::DRAW_STAGE_ADVANCE => 'По результатам предыдущей стадии',
            self::DRAW_LEAGUE_CARRY  => 'По составу лиги',
            self::DRAW_MANUAL        => 'Ручное распределение',
            default                  => 'Посев по рейтингу',
        };
    }
}
