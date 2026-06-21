<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AthleteDevice extends Model
{
    protected $fillable = ['user_id', 'name', 'protocol', 'ble_identifier', 'model', 'last_connected_at'];

    protected $casts = [
        'last_connected_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ActivitySession::class, 'device_id');
    }

    /** Возможности этого устройства на основе protocol. */
    public function capabilities(): array
    {
        return config('activity.device_capabilities')[$this->protocol]
            ?? config('activity.default_capabilities', ['hr']);
    }
}
