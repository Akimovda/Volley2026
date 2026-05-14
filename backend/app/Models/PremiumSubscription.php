<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PremiumSubscription extends Model
{
    protected $fillable = [
        'user_id', 'plan', 'status', 'starts_at', 'expires_at',
        'referred_by', 'payment_id',
        'weekly_digest', 'notify_level_min', 'notify_level_max', 'notify_city_id',
        'hide_from_followers',
    ];

    protected $casts = [
        'starts_at'           => 'datetime',
        'expires_at'          => 'datetime',
        'hide_from_followers' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Payment::class, 'payment_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->expires_at->isFuture();
    }

    public static function planDays(string $plan): int
    {
        return match($plan) {
            'trial'   => 7,
            'month'   => 30,
            'quarter' => 90,
            'year'    => 365,
            default   => 30,
        };
    }
}
