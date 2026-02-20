<?php

namespace App\Models;

use Carbon\Carbon;
use App\Support\DateTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Event;
use App\Models\EventRegistration;


class EventOccurrence extends Model
{
    protected $table = 'event_occurrences';

    protected $fillable = [
        'event_id',
        'starts_at',
        'ends_at',
        'timezone',
        'is_cancelled',
        'cancelled_at',
        'uniq_key',

        // snapshot/override fields
        'location_id',
        'allow_registration',
        'max_players',
        'classic_level_min',
        'classic_level_max',
        'beach_level_min',
        'beach_level_max',
        'registration_starts_at',
        'registration_ends_at',
        'cancel_self_until',
    ];

    protected $casts = [
        // ВАЖНО: starts_at/ends_at в БД — UTC (timestamp/timestamptz), но Laravel кастит в app.timezone.
        // Поэтому "local" делаем через raw-значение + явный UTC (см. accessors ниже).
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'cancelled_at' => 'datetime',

        'is_cancelled' => 'boolean',
        'location_id' => 'integer',
        'allow_registration' => 'boolean',
        'max_players' => 'integer',

        'classic_level_min' => 'integer',
        'classic_level_max' => 'integer',
        'beach_level_min' => 'integer',
        'beach_level_max' => 'integer',

        // snapshot window (абсолютные UTC даты для occurrence)
        'registration_starts_at' => 'datetime',
        'registration_ends_at' => 'datetime',
        'cancel_self_until' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class, 'occurrence_id');
    }

    /**
     * starts_at_local / ends_at_local:
     * starts_at/ends_at храним в UTC, а timezone используем для отображения.
     * Берём raw-значение из БД и явно считаем его UTC -> переводим в TZ occurrence.
     */
    public function getStartsAtLocalAttribute(): ?Carbon
    {
        $raw = $this->getRawOriginal('starts_at');
        if (!$raw) return null;

        $tz = $this->timezone ?: ($this->event?->timezone ?: 'UTC');

        return DateTime::fromUtcToTz($raw, $tz);
    }

    public function getEndsAtLocalAttribute(): ?Carbon
    {
        $raw = $this->getRawOriginal('ends_at');
        if (!$raw) return null;

        $tz = $this->timezone ?: ($this->event?->timezone ?: 'UTC');

        return DateTime::fromUtcToTz($raw, $tz);
    }

    // ---------------- Effective (override -> fallback to event) ----------------

    public function effectiveLocationId(): ?int
    {
        return $this->location_id ?? $this->event?->location_id;
    }

    public function effectiveAllowRegistration(): bool
    {
        return (bool) ($this->allow_registration ?? $this->event?->allow_registration ?? false);
    }

    public function effectiveMaxPlayers(): int
    {
        return (int) ($this->max_players ?? $this->event?->gameSettings?->max_players ?? 0);
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

    // registration window: приоритет occurrence snapshot -> fallback to event.*
    public function effectiveRegistrationStartsAt(): ?Carbon
    {
        return $this->registration_starts_at ?? $this->event?->registration_starts_at;
    }

    public function effectiveRegistrationEndsAt(): ?Carbon
    {
        return $this->registration_ends_at ?? $this->event?->registration_ends_at;
    }

    public function effectiveCancelSelfUntil(): ?Carbon
    {
        return $this->cancel_self_until ?? $this->event?->cancel_self_until;
    }
}
