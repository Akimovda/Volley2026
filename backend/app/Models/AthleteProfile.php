<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AthleteProfile extends Model
{
    protected $fillable = ['user_id', 'resting_hr', 'max_hr', 'weight_kg', 'reach_classic_cm', 'reach_beach_cm'];

    protected $casts = [
        'resting_hr'      => 'integer',
        'max_hr'          => 'integer',
        'weight_kg'       => 'decimal:2',
        'reach_classic_cm'=> 'integer',
        'reach_beach_cm'  => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
