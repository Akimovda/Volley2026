<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActivitySession extends Model
{
    protected $fillable = [
        'user_id', 'occurrence_id', 'device_id', 'direction', 'status',
        'source', 'external_workout_id', 'client_uuid',
        'started_at', 'ended_at', 'duration_sec',
        'avg_hr', 'max_hr', 'min_hr',
        'time_in_zone', 'load_score', 'samples_count', 'calories_kcal', 'calorie_source',
        'jump_count', 'jump_avg_height_cm', 'jump_max_height_cm', 'tracked_capabilities',
        'steps', 'jump_count_expected', 'jump_count_mismatch',
    ];

    protected $casts = [
        'started_at'           => 'datetime',
        'ended_at'             => 'datetime',
        'time_in_zone'         => 'array',
        'tracked_capabilities' => 'array',
        'load_score'           => 'decimal:2',
        'avg_hr'               => 'integer',
        'max_hr'               => 'integer',
        'min_hr'               => 'integer',
        'duration_sec'         => 'integer',
        'samples_count'        => 'integer',
        'jump_count'           => 'integer',
        'jump_count_expected'  => 'integer',
        'jump_count_mismatch'  => 'integer',
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

    public function jumps(): HasMany
    {
        return $this->hasMany(ActivityJumpEvent::class, 'session_id');
    }

    public function getSourceNameAttribute(): string
    {
        return match($this->source) {
            'healthkit_import' => 'Здоровье',
            'ble'              => 'BLE',
            'watch'            => 'Apple Watch',
            default            => $this->source ?? 'watch',
        };
    }

    /**
     * 'completed' — finalize() отработал; 'pending' — ещё не финализирована, в пределах окна ожидания;
     * 'stale' — не финализирована дольше activity.sync_stale_hours, данные, вероятно, не придут.
     */
    public function getSyncStatusAttribute(): string
    {
        if ($this->status === 'completed') {
            return 'completed';
        }

        $ageHours = (now()->timestamp - $this->started_at->timestamp) / 3600;

        return $ageHours < config('activity.sync_stale_hours', 6) ? 'pending' : 'stale';
    }
}
