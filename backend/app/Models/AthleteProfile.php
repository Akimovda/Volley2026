<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AthleteProfile extends Model
{
    protected $fillable = [
        'user_id', 'resting_hr', 'max_hr', 'weight_kg',
        'reach_classic_cm', 'reach_beach_cm', 'jump_height_coeff',
        'preferred_device_type', 'preferred_device_id',
    ];

    protected $casts = [
        'resting_hr'          => 'integer',
        'max_hr'              => 'integer',
        'weight_kg'           => 'decimal:2',
        'reach_classic_cm'    => 'integer',
        'reach_beach_cm'      => 'integer',
        'jump_height_coeff'   => 'decimal:3',
        'preferred_device_id' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function preferredDevice(): BelongsTo
    {
        return $this->belongsTo(AthleteDevice::class, 'preferred_device_id');
    }
}
