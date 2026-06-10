<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerRatingHistory extends Model
{
    protected $table = 'player_rating_history';
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'event_id', 'match_id',
        'mu_before', 'mu_after', 'sigma_before', 'sigma_after',
        'recorded_at', 'created_at',
    ];

    protected $casts = [
        'mu_before' => 'float', 'mu_after' => 'float',
        'sigma_before' => 'float', 'sigma_after' => 'float',
        'mu_delta' => 'float', 'sigma_delta' => 'float',
        'recorded_at' => 'datetime', 'created_at' => 'datetime',
    ];

    public function user(): BelongsTo    { return $this->belongsTo(User::class); }
    public function event(): BelongsTo   { return $this->belongsTo(Event::class); }
}
