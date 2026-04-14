<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizerSubscription extends Model
{
    protected $fillable = [
        'user_id',
        'plan',
        'status',
        'starts_at',
        'expires_at',
        'payment_method',
        'payment_id',
        'amount_rub',
        'auto_renew',
    ];

    protected $casts = [
        'starts_at'  => 'datetime',
        'expires_at' => 'datetime',
        'auto_renew' => 'boolean',
        'amount_rub' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->expires_at->isFuture();
    }

    public static function planLabel(string $plan): string
    {
        return match ($plan) {
            'trial'   => '7 дней бесплатно',
            'month'   => '1 месяц',
            'quarter' => '3 месяца',
            'year'    => '1 год',
            default   => $plan,
        };
    }

    public static function planDays(string $plan): int
    {
        return match ($plan) {
            'trial'   => 7,
            'month'   => 30,
            'quarter' => 90,
            'year'    => 365,
            default   => 30,
        };
    }

    public static function planPrice(string $plan): int
    {
        return match ($plan) {
            'trial'   => 0,
            'month'   => 499,
            'quarter' => 1199,
            'year'    => 3999,
            default   => 0,
        };
    }
}
