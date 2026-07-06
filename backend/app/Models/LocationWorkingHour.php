<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationWorkingHour extends Model
{
    protected $fillable = [
        'direction_id',
        'day_of_week',
        'opens_at',
        'closes_at',
        'is_day_off',
    ];

    protected $casts = [
        'is_day_off' => 'boolean',
        'day_of_week' => 'integer',
    ];

    public function direction(): BelongsTo
    {
        return $this->belongsTo(LocationDirection::class, 'direction_id');
    }
}
