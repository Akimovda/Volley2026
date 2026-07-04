<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourtPriceRule extends Model
{
    protected $fillable = [
        'direction_id',
        'court_id',
        'day_of_week',
        'starts_at',
        'ends_at',
        'price_per_hour',
        'priority',
    ];

    protected $casts = [
        'day_of_week'    => 'integer',
        'price_per_hour' => 'float',
        'priority'       => 'integer',
    ];

    public function direction(): BelongsTo
    {
        return $this->belongsTo(LocationDirection::class, 'direction_id');
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(LocationCourt::class, 'court_id');
    }
}
