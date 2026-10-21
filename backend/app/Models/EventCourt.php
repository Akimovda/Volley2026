<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/** Площадка трансляции (корт). Не путать со строкой tournament_matches.court (она остаётся как есть). */
class EventCourt extends Model
{
    protected $fillable = ['event_id', 'occurrence_id', 'name', 'sort_order', 'is_active', 'channel_key'];

    protected $casts = ['is_active' => 'boolean'];

    protected static function booted(): void
    {
        static::creating(function (self $c) {
            if (empty($c->channel_key)) {
                $c->channel_key = Str::random(32);
            }
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class, 'occurrence_id');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(TournamentMatch::class, 'court_id');
    }

    public function override(): HasOne
    {
        return $this->hasOne(BroadcastCourtOverride::class, 'court_id');
    }
}
