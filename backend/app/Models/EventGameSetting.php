<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventGameSetting extends Model
{
    protected $table = 'event_game_settings';

    protected $fillable = [
        'event_id',
        'subtype',
        'libero_mode',
        'min_players',
        'max_players',
        'positions',
        // ✅ gender policy
        'gender_policy',
        'gender_limited_side',
        'gender_limited_max',
        'gender_limited_positions',
        // (legacy поля можно оставить, если они есть в таблице)
        'allow_girls',
        'girls_max',
    ];

    protected $casts = [
        'positions' => 'array',
        'gender_limited_positions' => 'array',
        'min_players' => 'integer',
        'max_players' => 'integer',
        'gender_limited_max' => 'integer',
        'allow_girls' => 'boolean',
        'girls_max' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
