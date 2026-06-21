<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActivitySession extends Model
{
    protected $fillable = [
        'user_id', 'occurrence_id', 'device_id', 'direction', 'status',
        'started_at', 'ended_at', 'duration_sec',
        'avg_hr', 'max_hr', 'min_hr',
        'time_in_zone', 'load_score', 'samples_count',
    ];

    protected $casts = [
        'started_at'   => 'datetime',
        'ended_at'     => 'datetime',
        'time_in_zone' => 'array',
        'load_score'   => 'decimal:2',
        'avg_hr'       => 'integer',
        'max_hr'       => 'integer',
        'min_hr'       => 'integer',
        'duration_sec' => 'integer',
        'samples_count'=> 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class, 'occurrence_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(AthleteDevice::class, 'device_id');
    }

    public function samples(): HasMany
    {
        return $this->hasMany(ActivityHrSample::class, 'session_id');
    }
}
