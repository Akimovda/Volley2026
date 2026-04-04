<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventNotificationChannel extends Model
{
    protected $fillable = [
        'event_id',
        'channel_id',
        'notification_type',
        'use_private_link',
        'silent',
        'update_message',
        'include_image',
        'include_registered_list',
    ];

    protected $casts = [
        'use_private_link' => 'boolean',
        'silent' => 'boolean',
        'update_message' => 'boolean',
        'include_image' => 'boolean',
        'include_registered_list' => 'boolean',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(UserNotificationChannel::class, 'channel_id');
    }
}