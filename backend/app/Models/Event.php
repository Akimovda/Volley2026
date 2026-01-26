<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Event extends Model
{
    protected $fillable = [
        // ✅ старое (НЕ трогаем)
        'title',
        'requires_personal_data',
        'classic_level_min',
        'beach_level_min',

        // ✅ уже добавленное тобой
        'organizer_id',
        'location_id',
        'timezone',
        'starts_at',
        'ends_at',
        'is_private',
        'direction',
        'format',
        'allow_registration',
        'is_recurring',
        'recurrence_rule',

        // ✅ доп. поля, которые уже реально есть в БД (чтобы можно было сохранять без forceFill)
        'sport_category',
        'event_format',
        'visibility',
        'public_token',
        'rrule',
        'is_registrable',
        'is_paid',
        'price_text',
    ];

    protected $casts = [
        // ✅ старое (НЕ трогаем)
        'requires_personal_data' => 'boolean',
        'classic_level_min' => 'integer',
        'beach_level_min' => 'integer',

        // ✅ уже добавленное тобой
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_private' => 'boolean',
        'allow_registration' => 'boolean',
        'is_recurring' => 'boolean',

        // ✅ доп.
        'is_registrable' => 'boolean',
        'is_paid' => 'boolean',
    ];

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_registrations')
            ->withTimestamps();
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }
}
