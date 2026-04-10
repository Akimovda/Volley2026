<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionUsage extends Model
{
    protected $fillable = [
        'subscription_id', 'user_id', 'event_id', 'occurrence_id',
        'registration_id', 'action', 'used_at', 'returned_at', 'note',
    ];

    protected $casts = [
        'used_at'     => 'datetime',
        'returned_at' => 'datetime',
    ];

    public function subscription(): BelongsTo { return $this->belongsTo(Subscription::class); }
    public function user(): BelongsTo         { return $this->belongsTo(User::class); }
    public function event(): BelongsTo        { return $this->belongsTo(Event::class); }

    public function scopeUsed($q)     { return $q->where('action', 'used'); }
    public function scopeReturned($q) { return $q->where('action', 'returned'); }
    public function scopeBurned($q)   { return $q->where('action', 'burned'); }
}
