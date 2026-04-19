<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventTournamentSetting extends Model
{
    protected $table = 'event_tournament_settings';

    protected $fillable = [
        'event_id',
        'registration_mode',

        // базовые лимиты (старые)
        'team_size_min',
        'team_size_max',

        // правила
        'require_libero',
        'max_rating_sum',
        'allow_reserves',
        'captain_confirms_members',
        'auto_submit_when_ready',
        'seeding_mode',
        'application_mode',

        // JSON
        'meta',

        // 🔥 НОВОЕ (ключевое)
        'game_scheme',
        'reserve_players_max',
        'total_players_max',
        'payment_mode',
    ];

    protected $casts = [
        'require_libero' => 'boolean',
        'allow_reserves' => 'boolean',
        'captain_confirms_members' => 'boolean',
        'auto_submit_when_ready' => 'boolean',
        'application_mode' => 'string',

        'team_size_min' => 'integer',
        'team_size_max' => 'integer',
        'max_rating_sum' => 'integer',

        'reserve_players_max' => 'integer',
        'total_players_max' => 'integer',

        'meta' => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers (очень важно для сервисов)
    |--------------------------------------------------------------------------
    */

    /**
     * Схема игры (5x1, 2x2 и т.д.)
     */
    public function getGameScheme(): string
    {
        return $this->game_scheme
            ?? data_get($this->meta, 'game_scheme')
            ?? '5x1';
    }

    /**
     * Максимальный размер команды
     */
   public function getReserveMax(): ?int
    {
        return $this->reserve_players_max
            ?? data_get($this->meta, 'reserve_players_max');
    }
    
    public function getTotalMax(): ?int
    {
        return $this->total_players_max
            ?? $this->team_size_max
            ?? data_get($this->meta, 'total_players_max');
    }

    /**
     * Тип турнира (удобный хелпер)
     */
    public function isBeach(): bool
    {
        return $this->registration_mode === 'team_beach';
    }

    public function isClassic(): bool
    {
        return !$this->isBeach();
    }

    public const PAYMENT_FREE       = 'free';
    public const PAYMENT_TEAM       = 'team';
    public const PAYMENT_PER_PLAYER = 'per_player';

    public function paymentMode(): string
    {
        return $this->payment_mode ?? self::PAYMENT_FREE;
    }

    public function isPaymentRequired(): bool
    {
        return $this->paymentMode() !== self::PAYMENT_FREE;
    }

    public function isTeamPayment(): bool
    {
        return $this->paymentMode() === self::PAYMENT_TEAM;
    }

    public function isPerPlayerPayment(): bool
    {
        return $this->paymentMode() === self::PAYMENT_PER_PLAYER;
    }

}