<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LocationDirection extends Model
{
    public const DIRECTION_CLASSIC = 'classic';
    public const DIRECTION_BEACH = 'beach';

    protected $fillable = [
        'location_id',
        'direction',
        'courts_count',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'courts_count' => 'integer',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function courts(): HasMany
    {
        return $this->hasMany(LocationCourt::class, 'direction_id');
    }

    public function workingHours(): HasMany
    {
        return $this->hasMany(LocationWorkingHour::class, 'direction_id');
    }
}
