<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserBeachZone extends Model
{
    protected $table = 'user_beach_zones';

    protected $fillable = [
        'user_id',
        'zone',
        'is_primary',
    ];

    protected $casts = [
        'zone' => 'integer',
        'is_primary' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
