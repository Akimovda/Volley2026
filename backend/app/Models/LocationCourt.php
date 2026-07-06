<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationCourt extends Model
{
    protected $fillable = [
        'direction_id',
        'name',
        'is_indoor',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_indoor' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function direction(): BelongsTo
    {
        return $this->belongsTo(LocationDirection::class, 'direction_id');
    }
}
