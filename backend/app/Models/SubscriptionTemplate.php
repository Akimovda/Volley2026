<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionTemplate extends Model
{
    protected $fillable = [
        'organizer_id', 'name', 'description', 'event_ids',
        'valid_from', 'valid_until', 'duration_months', 'duration_days', 'visits_total',
        'cancel_hours_before', 'freeze_enabled', 'freeze_max_weeks', 'freeze_max_months',
        'transfer_enabled', 'auto_booking_enabled',
        'price_minor', 'currency', 'sale_limit', 'sold_count', 'sale_enabled', 'is_active',
    ];

    protected $casts = [
        'event_ids'             => 'array',
        'valid_from'            => 'date',
        'valid_until'           => 'date',
        'freeze_enabled'        => 'boolean',
        'transfer_enabled'      => 'boolean',
        'auto_booking_enabled'  => 'boolean',
        'sale_enabled'          => 'boolean',
        'is_active'             => 'boolean',
    ];

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'template_id');
    }

    public function getPriceAttribute(): float
    {
        return $this->price_minor / 100;
    }

    public function isSoldOut(): bool
    {
        return $this->sale_limit !== null && $this->sold_count >= $this->sale_limit;
    }

    public function appliesToEvent(int $eventId): bool
    {
        if (empty($this->event_ids)) return true; // null = все мероприятия
        return in_array($eventId, $this->event_ids);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeForOrganizer($q, int $organizerId)
    {
        return $q->where('organizer_id', $organizerId);
    }
}
