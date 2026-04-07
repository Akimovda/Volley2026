<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OccurrenceWaitlist extends Model
{
    protected $table = 'occurrence_waitlist';

    protected $fillable = [
        'occurrence_id',
        'user_id',
        'positions',
        'notified_at',
        'notification_expires_at',
    ];

    protected $casts = [
        'positions'                => 'array',
        'notified_at'              => 'datetime',
        'notification_expires_at'  => 'datetime',
    ];

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class, 'occurrence_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isNotificationActive(): bool
    {
        return $this->notification_expires_at
            && $this->notification_expires_at->isFuture();
    }

    public function subscribedToPosition(string $position): bool
    {
        $positions = $this->positions ?? [];
        return empty($positions) || in_array($position, $positions, true);
    }
}
