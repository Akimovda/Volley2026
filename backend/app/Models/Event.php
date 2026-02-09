<?php
// app/Models/Event.php

namespace App\Models;

use App\Models\EventOccurrence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

// ✅ Spatie Media Library
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Event extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'title',
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
        'ends_at',

        'is_private',
        'visibility',
        'public_token',

        'direction',
        'format',
        'allow_registration',

        'is_recurring',
        'recurrence_rule',

        'is_paid',
        'price_text',

        // ✅ templates via events
        'is_template',
    ];

    protected $casts = [
        'requires_personal_data' => 'boolean',

        'classic_level_min' => 'integer',
        'classic_level_max' => 'integer',
        'beach_level_min' => 'integer',
        'beach_level_max' => 'integer',

        'starts_at' => 'datetime',
        'ends_at' => 'datetime',

        'is_private' => 'boolean',
        'allow_registration' => 'boolean',
        'is_recurring' => 'boolean',
        'is_paid' => 'boolean',

        'is_template' => 'boolean',
    ];

    // ✅ Обложка события (1 файл)
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')->singleFile();
    }
    
    public function occurrences()
    {
        return $this->hasMany(EventOccurrence::class);
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_registrations')->withTimestamps();
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function gameSettings(): HasOne
    {
        return $this->hasOne(\App\Models\EventGameSetting::class);
    }
}
