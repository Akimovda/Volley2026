<?php

namespace App\Models;

use Carbon\Carbon;
use App\Support\DateTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventOccurrence extends Model
{

    protected $table = 'event_occurrences';

    protected $fillable = [
        'event_id',
        'title',
        'description_html',
        'starts_at',
        'duration_sec',
        'timezone',
        'is_cancelled',
        'cancelled_at',
        'uniq_key',

        'location_id',
        'allow_registration',
        'max_players',
        'show_participants',

        'classic_level_min',
        'classic_level_max',
        'beach_level_min',
        'beach_level_max',

        'registration_starts_at',
        'registration_ends_at',
        'cancel_self_until',

        'remind_registration_enabled',
        'remind_registration_minutes_before',

        'age_policy',
        'child_age_min',
        'child_age_max',
        'is_snow',

        // payment
        'is_paid',
        'price_minor',
        'price_currency',
        'price_text',
        'payment_method',
        'payment_link',

        // refund
        'refund_hours_full',
        'refund_hours_partial',
        'refund_partial_pct',

        // misc
        'trainer_user_id',
        'requires_personal_data',
    ];

    protected $casts = [
        // starts_at всегда в UTC в БД
        'starts_at' => 'datetime',
        'duration_sec' => 'integer',
        'cancelled_at' => 'datetime',
        'is_cancelled' => 'boolean',

        'location_id' => 'integer',
        'allow_registration' => 'boolean',
        'max_players' => 'integer',

        'classic_level_min' => 'integer',
        'classic_level_max' => 'integer',
        'beach_level_min' => 'integer',
        'beach_level_max' => 'integer',

        // registration windows (UTC)
        'registration_starts_at' => 'datetime',
        'registration_ends_at' => 'datetime',
        'cancel_self_until' => 'datetime',

        'age_policy' => 'string',
        'child_age_min' => 'integer',
        'child_age_max' => 'integer',
        'is_snow' => 'boolean',

        'show_participants' => 'boolean',
        'remind_registration_enabled' => 'boolean',
        'remind_registration_minutes_before' => 'integer',

        'is_paid' => 'boolean',
        'price_minor' => 'integer',
        'refund_hours_full' => 'integer',
        'refund_hours_partial' => 'integer',
        'refund_partial_pct' => 'integer',
        'trainer_user_id' => 'integer',
        'requires_personal_data' => 'boolean',
    ];
    /* ===================== Teams ===================== */
     /**
     * Override игровых настроек per-occurrence.
     * hasOne: запись либо есть (override), либо нет (наследуем от event_game_settings).
     */
    public function gameSettingsOverride()
    {
        return $this->hasOne(\App\Models\EventOccurrenceGameSetting::class, 'occurrence_id');
    }

    /**
     * Эффективные игровые настройки (override → fallback на event).
     * Возвращает объект с полями subtype, teams_count, min_players, max_players и т.д.
     * Значения override имеют приоритет, NULL поля override наследуются от event_game_settings.
     */
    public function effectiveGameSettings(): object
    {
        $override = $this->gameSettingsOverride;
        $eventSettings = $this->event?->gameSettings;

        $fields = [
            'subtype', 'teams_count', 'libero_mode',
            'min_players', 'max_players', 'positions',
            'gender_policy', 'gender_limited_side', 'gender_limited_max',
            'gender_limited_positions', 'gender_limited_reg_starts_days_before',
            'allow_girls', 'girls_max',
        ];

        $result = [];
        foreach ($fields as $field) {
            $overrideValue = $override?->{$field};
            $result[$field] = ($overrideValue !== null)
                ? $overrideValue
                : ($eventSettings?->{$field} ?? null);
        }

        return (object) $result;
    }

    public function teams()
        {
            return $this->hasMany(\App\Models\EventTeam::class, 'occurrence_id');
        }
    /* ===================== Relations ===================== */

    public function event()
    {
        return $this->belongsTo(\App\Models\Event::class);
    }

    public function registrations()
    {
        return $this->hasMany(\App\Models\EventRegistration::class, 'occurrence_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Location::class);
    }

    /* ===================== Local time accessors ===================== */

    /**
     * starts_at_local
     * starts_at хранится в UTC, отображаем в TZ occurrence / event
     */
    public function getStartsAtLocalAttribute(): ?Carbon
    {
        $raw = $this->getRawOriginal('starts_at');

        if (!$raw) {
            return null;
        }

        $tz = $this->timezone ?: ($this->event?->timezone ?: 'UTC');

        return DateTime::fromUtcToTz($raw, $tz);
    }

    /**
     * ends_at_local (вычисляемый)
     * ends_at = starts_at + duration_sec
     */
    public function getEndsAtLocalAttribute(): ?Carbon
    {
        $rawStart = $this->getRawOriginal('starts_at');

        if (!$rawStart || !$this->duration_sec) {
            return null;
        }

        $tz = $this->timezone ?: ($this->event?->timezone ?: 'UTC');

        return \Carbon\Carbon::parse($rawStart, 'UTC')
            ->setTimezone($tz)
            ->addSeconds((int)$this->duration_sec);
    }

    /**
     * ends_at_utc (иногда удобно для логики)
     */
    public function getEndsAtUtcAttribute(): ?Carbon
    {
        if (!$this->starts_at || !$this->duration_sec) {
            return null;
        }

        return Carbon::parse($this->starts_at, 'UTC')
            ->addSeconds((int)$this->duration_sec);
    }

    /* ===================== Effective values ===================== */

    public function effectiveLocationId(): ?int
    {
        return $this->location_id ?? $this->event?->location_id;
    }

    public function effectiveAllowRegistration(): bool
    {
        return (bool)(
            $this->allow_registration
            ?? $this->event?->allow_registration
            ?? false
        );
    }

    public function effectiveMaxPlayers(): int
    {
        return (int)(
            $this->max_players
            ?? $this->event?->gameSettings?->max_players
            ?? 0
        );
    }

    public function effectiveClassicLevelMin(): ?int
    {
        return $this->classic_level_min ?? $this->event?->classic_level_min;
    }

    public function effectiveClassicLevelMax(): ?int
    {
        return $this->classic_level_max ?? $this->event?->classic_level_max;
    }

    public function effectiveBeachLevelMin(): ?int
    {
        return $this->beach_level_min ?? $this->event?->beach_level_min;
    }

    public function effectiveBeachLevelMax(): ?int
    {
        return $this->beach_level_max ?? $this->event?->beach_level_max;
    }

    /* ===================== Registration windows ===================== */

    public function effectiveRegistrationStartsAt(): ?Carbon
    {
        return $this->registration_starts_at
            ?? $this->event?->registration_starts_at;
    }

    public function effectiveRegistrationEndsAt(): ?Carbon
    {
        return $this->registration_ends_at
            ?? $this->event?->registration_ends_at;
    }

    public function effectiveCancelSelfUntil(): ?Carbon
    {
        return $this->cancel_self_until
            ?? $this->event?->cancel_self_until;
    }
    /* ===================== Status helpers ===================== */

    public function isStarted(): bool
    {
        if (!$this->starts_at) {
            return false;
        }
    
        return now('UTC')->greaterThanOrEqualTo($this->starts_at);
    }
    
    public function isFinished(): bool
    {
        $ends = $this->ends_at_utc;
    
        if (!$ends) {
            return false;
        }
    
        return now('UTC')->greaterThanOrEqualTo($ends);
    }
    

    public function isCancelled(): bool
    {
        return !empty($this->cancelled_at) || (bool)($this->is_cancelled ?? false);
    }
    public function isRunning(): bool
    {
        return $this->isStarted() && !$this->isFinished();
    }


    /**
     * Тренеры (multi) именно на этом occurrence (override).
     * Если коллекция пуста → считается, что тренеры наследуются от event->trainers.
     */
    public function trainers()
    {
        return $this->belongsToMany(
            \App\Models\User::class,
            'event_occurrence_trainers',
            'occurrence_id',
            'user_id'
        )->withTimestamps();
    }
}