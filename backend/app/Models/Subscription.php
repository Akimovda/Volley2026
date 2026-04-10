<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Subscription extends Model
{
    protected $fillable = [
        'user_id', 'template_id', 'organizer_id',
        'starts_at', 'expires_at',
        'visits_total', 'visits_used', 'visits_remaining',
        'status', 'frozen_at', 'frozen_until',
        'auto_booking', 'auto_booking_event_ids',
        'payment_id', 'payment_status',
        'issued_by', 'issue_reason',
    ];

    protected $casts = [
        'starts_at'              => 'date',
        'expires_at'             => 'date',
        'frozen_at'              => 'date',
        'frozen_until'           => 'date',
        'auto_booking'           => 'boolean',
        'auto_booking_event_ids' => 'array',
    ];

    public function user(): BelongsTo     { return $this->belongsTo(User::class); }
    public function template(): BelongsTo { return $this->belongsTo(SubscriptionTemplate::class); }
    public function organizer(): BelongsTo { return $this->belongsTo(User::class, 'organizer_id'); }
    public function issuedBy(): BelongsTo  { return $this->belongsTo(User::class, 'issued_by'); }
    public function usages(): HasMany      { return $this->hasMany(SubscriptionUsage::class); }

    public function isActive(): bool   { return $this->status === 'active'; }
    public function isFrozen(): bool   { return $this->status === 'frozen'; }
    public function isExpired(): bool  { return $this->status === 'expired'; }
    public function isExhausted(): bool { return $this->status === 'exhausted'; }

    public function hasVisitsLeft(): bool
    {
        return $this->visits_remaining > 0;
    }

    public function isValidForDate(?Carbon $date = null): bool
    {
        $date = $date ?? now();
        if ($this->starts_at && $date->lt($this->starts_at)) return false;
        if ($this->expires_at && $date->gt($this->expires_at)) return false;
        return true;
    }

    public function isUsableForEvent(int $eventId): bool
    {
        if (!$this->isActive()) return false;
        if (!$this->hasVisitsLeft()) return false;
        if (!$this->isValidForDate()) return false;
        return $this->template->appliesToEvent($eventId);
    }

    public function scopeActive($q)    { return $q->where('status', 'active'); }
    public function scopeForUser($q, int $userId) { return $q->where('user_id', $userId); }
}
