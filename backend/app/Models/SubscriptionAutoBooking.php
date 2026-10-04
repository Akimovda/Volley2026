<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionAutoBooking extends Model
{
    protected $fillable = ['subscription_id', 'user_id', 'event_id', 'position'];

    public function subscription(): BelongsTo { return $this->belongsTo(Subscription::class); }
    public function user(): BelongsTo         { return $this->belongsTo(User::class); }
    public function event(): BelongsTo        { return $this->belongsTo(Event::class); }
}
