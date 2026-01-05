<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Event extends Model
{
    protected $fillable = [
        'title',
        'requires_personal_data',
        'classic_level_min',
        'beach_level_min',
    ];

    protected $casts = [
        'requires_personal_data' => 'boolean',
        'classic_level_min' => 'integer',
        'beach_level_min' => 'integer',
    ];

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_registrations')
            ->withTimestamps();
    }
}
