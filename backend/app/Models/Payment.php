<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'user_id', 'organizer_id', 'event_id', 'occurrence_id', 'registration_id',
        'method', 'status', 'amount_minor', 'currency',
        'yoomoney_payment_id', 'yoomoney_confirmation_url', 'yoomoney_meta',
        'expires_at',
        'user_confirmed', 'org_confirmed', 'user_confirmed_at', 'org_confirmed_at',
        'refund_amount_minor', 'refund_reason', 'refunded_at',
    ];

    protected $casts = [
        'expires_at'          => 'datetime',
        'user_confirmed_at'   => 'datetime',
        'org_confirmed_at'    => 'datetime',
        'refunded_at'         => 'datetime',
        'user_confirmed'      => 'boolean',
        'org_confirmed'       => 'boolean',
        'yoomoney_meta'       => 'array',
    ];

    public function user()         { return $this->belongsTo(User::class); }
    public function organizer()    { return $this->belongsTo(User::class, 'organizer_id'); }
    public function event()        { return $this->belongsTo(Event::class); }
    public function registration() { return $this->belongsTo(EventRegistration::class); }

    public function isPending(): bool  { return $this->status === 'pending'; }
    public function isPaid(): bool     { return $this->status === 'paid'; }
    public function isExpired(): bool  { return $this->expires_at && now()->gte($this->expires_at); }

    public function getAmountAttribute(): float { return $this->amount_minor / 100; }

    public function isLinkConfirmed(): bool
    {
        return $this->user_confirmed && $this->org_confirmed;
    }

    public function scopePending($q)  { return $q->where('status', 'pending'); }
    public function scopePaid($q)     { return $q->where('status', 'paid'); }
    public function scopeExpired($q)  { return $q->where('status', 'pending')->where('expires_at', '<', now()); }
}
