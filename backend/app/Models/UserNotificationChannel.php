<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserNotificationChannel extends Model
{
    protected $fillable = [
        'user_id',
        'platform',
        'title',
        'chat_id',
        'is_verified',
        'verified_at',
        'meta',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
        'meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bindRequests(): HasMany
    {
        return $this->hasMany(ChannelBindRequest::class, 'channel_id');
    }

    public function eventChannels(): HasMany
    {
        return $this->hasMany(EventNotificationChannel::class, 'channel_id');
    }

    public function eventMessages(): HasMany
    {
        return $this->hasMany(EventChannelMessage::class, 'channel_id');
    }

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }
}
