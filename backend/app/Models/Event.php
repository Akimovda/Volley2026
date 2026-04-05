<?php

namespace App\Models;

use App\Models\EventOccurrence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

// Spatie Media Library
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Event extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'title',
        'description_html',
        'requires_personal_data',
        'classic_level_min',
        'classic_level_max',
        'beach_level_min',
        'beach_level_max',
        'trainer_user_id',
        'organizer_id',
        'location_id',
        'timezone',
        'starts_at',
        'duration_sec',
        'is_private',
        'visibility',
        'public_token',
        'direction',
        'format',
        'allow_registration',
        'registration_mode',
        'tournament_teams_count',

        // registration windows
        'registration_starts_at',
        'registration_ends_at',
        'cancel_self_until',

        'is_recurring',
        'recurrence_rule',
        'child_age_min',
        'child_age_max',

        'is_paid',
        'price_minor',
        'price_currency',
        'price_text',
        'bot_assistant_enabled',
        'bot_assistant_threshold',
        'bot_assistant_max_fill_pct',
        'is_template',
		'event_photos',
    ];

    protected $casts = [
        'requires_personal_data' => 'boolean',

        'classic_level_min' => 'integer',
        'classic_level_max' => 'integer',
        'beach_level_min' => 'integer',
        'beach_level_max' => 'integer',

        'starts_at' => 'datetime',
        'duration_sec' => 'integer',

        'registration_starts_at' => 'datetime',
        'registration_ends_at' => 'datetime',
        'cancel_self_until' => 'datetime',

        'is_private' => 'boolean',
        'allow_registration' => 'boolean',
        'bot_assistant_enabled' => 'boolean',
        'bot_assistant_threshold' => 'integer',
        'bot_assistant_max_fill_pct' => 'integer',
        'is_recurring' => 'boolean',
        'is_paid' => 'boolean',
        'price_minor' => 'integer',
        'price_currency' => 'string',
        'child_age_min' => 'integer',
        'child_age_max' => 'integer',
        'is_template' => 'boolean',
		'event_photos' => 'array',
    ];

    /* ===================== Media ===================== */

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')->singleFile();
    }

    /* ===================== Relations ===================== */

    public function roleSlots(): HasMany
    {
        return $this->hasMany(\App\Models\EventRoleSlot::class);
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(EventOccurrence::class);
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_registrations')
            ->withTimestamps();
    }
    
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id')
            ->withTrashed();
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }
    public function tournamentSetting()
    {
        return $this->hasOne(EventTournamentSetting::class);
    }
    public function gameSettings(): HasOne
    {
        return $this->hasOne(
            EventGameSetting::class,
            'event_id',
            'id'
        );
    }
    public function notificationChannels()
    {
        return $this->hasMany(\App\Models\EventNotificationChannel::class);
    }
    /* ===================== Treners ===================== */
            public function trainers()
        {
            return $this->belongsToMany(
                \App\Models\User::class,
                'event_trainers',
                'event_id',
                'user_id'
            );
        }

    /* ===================== Computed times ===================== */

    /**
     * ends_at_utc = starts_at + duration_sec
     */
    public function getEndsAtUtcAttribute(): ?Carbon
    {
        if (!$this->starts_at || !$this->duration_sec) {
            return null;
        }

        return Carbon::parse($this->starts_at, 'UTC')
            ->addSeconds((int)$this->duration_sec);
    }

    /**
     * ends_at_local — для отображения
     */
    public function getEndsAtLocalAttribute(): ?Carbon
    {
        $endsUtc = $this->ends_at_utc;

        if (!$endsUtc) {
            return null;
        }

        $tz = $this->timezone ?: 'UTC';

        return $endsUtc->setTimezone($tz);
    }

}