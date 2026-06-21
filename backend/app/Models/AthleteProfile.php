<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AthleteProfile extends Model
{
    protected $fillable = ['user_id', 'resting_hr', 'max_hr', 'weight_kg'];

    protected $casts = [
        'resting_hr' => 'integer',
        'max_hr'     => 'integer',
        'weight_kg'  => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
