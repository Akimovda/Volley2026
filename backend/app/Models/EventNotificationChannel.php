<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventNotificationChannel extends Model
{
    /** Форматы, для которых доступен повтор анонса, и допустимые интервалы (дни). */
    public const REPEAT_FORMATS = ['camp', 'master_class', 'tournament', 'tournament_classic', 'tournament_beach'];
    public const REPEAT_DAYS    = [7, 14, 30]; // неделя, 2 недели, месяц (30 дней)

    protected $fillable = [
        'event_id',
        'channel_id',
        'notification_type',
        'use_private_link',
        'silent',
        'update_message',
        'include_image',
        'include_registered_list',
        'message_thread_id',
        'repeat_interval_days',
        'include_description',
    ];

    protected $casts = [
        'use_private_link'        => 'boolean',
        'silent'                  => 'boolean',
        'update_message'          => 'boolean',
        'include_image'           => 'boolean',
        'include_registered_list' => 'boolean',
        'message_thread_id'       => 'integer',
        'repeat_interval_days'    => 'integer',
        'include_description'     => 'boolean',
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