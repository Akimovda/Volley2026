<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourtBooking extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    public const ACTIVE_STATUSES = [self::STATUS_PENDING, self::STATUS_CONFIRMED, self::STATUS_PAID];

    public const PAYMENT_MODE_PREPAID = 'prepaid';
    public const PAYMENT_MODE_ON_SITE = 'on_site';
    public const PAYMENT_MODE_TRUSTED = 'trusted';

    protected $fillable = [
        'court_id',
        'user_id',
        'guest_name',
        'guest_phone',
        'event_id',
        'occurrence_id',
        'starts_at',
        'ends_at',
        'status',
        'price_total',
        'payment_mode',
        'expires_at',
        'cancelled_by',
        'cancel_reason',
    ];

    protected $casts = [
        'starts_at'   => 'datetime',
        'ends_at'     => 'datetime',
        'expires_at'  => 'datetime',
        'price_total' => 'float',
    ];

    public function court(): BelongsTo
    {
        return $this->belongsTo(LocationCourt::class, 'court_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class, 'occurrence_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereIn('status', self::ACTIVE_STATUSES);
    }

    /**
     * Бронь принадлежит ЛИБО пользователю платформы (user_id), ЛИБО гостю (guest_name).
     * User::name — единственный аксессор полного имени в проекте (full_name не существует).
     */
    public function getBookerNameAttribute(): string
    {
        return $this->user?->name ?? $this->guest_name ?? '—';
    }

    public function isGuestBooking(): bool
    {
        return $this->user_id === null && !empty($this->guest_name);
    }
}
