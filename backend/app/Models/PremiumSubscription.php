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
            'half'    => 180,
            'year'    => 365,
            default   => 30,
        };
    }

    public static function planLabel(string $plan): string
    {
        return match ($plan) {
            'trial'   => 'Пробный период',
            'month'   => '1 месяц',
            'quarter' => '3 месяца',
            'half'    => '6 месяцев',
            'year'    => '1 год',
            default   => $plan,
        };
    }

    /** Цена тарифа в копейках. month/quarter — только для уже созданных заявок (в продаже не участвуют). */
    public static function planPriceMinor(string $plan): int
    {
        return match ($plan) {
            'month'   => 19900,
            'quarter' => 49900,
            'half'    => self::planPriceRub('half') * 100,
            'year'    => self::planPriceRub('year') * 100,
            default   => 0,
        };
    }

    /** Цена продаваемого тарифа в рублях — из настроек платформы (админка), иначе значения по умолчанию. */
    public static function planPriceRub(string $plan): int
    {
        $s = \App\Models\PlatformPaymentSetting::first();

        return match ($plan) {
            'half'  => (int) ($s?->premium_half_rub ?? 2490),
            'year'  => (int) ($s?->premium_year_rub ?? 3990),
            default => 0,
        };
    }
}
