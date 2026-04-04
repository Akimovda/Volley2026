<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventChannelMessage extends Model
{
    protected $fillable = [
        'event_id',
        'occurrence_id',
        'channel_id',
        'platform',
        'external_chat_id',
        'external_message_id',
        'notification_type',
        'last_payload_hash',
        'sent_at',
        'last_synced_at',
        'meta',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'meta' => 'array',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class, 'occurrence_id');
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(UserNotificationChannel::class, 'channel_id');
    }
}
