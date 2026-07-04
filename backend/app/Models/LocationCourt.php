<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationCourt extends Model
{
    protected $fillable = [
        'direction_id',
        'name',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function direction(): BelongsTo
    {
        return $this->belongsTo(LocationDirection::class, 'direction_id');
    }
}
