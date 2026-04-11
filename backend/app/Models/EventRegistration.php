<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventRegistration extends Model
{
    protected $table = 'event_registrations';

    protected $fillable = [
        'event_id',
        'user_id',
        'occurrence_id',
        'position',
        'status',
        'cancelled_at',
    ];

    protected $casts = [
        'cancelled_at'      => 'datetime',
        'confirmed_at'      => 'datetime',
        'is_cancelled'      => 'boolean',
        'auto_booked'       => 'boolean',
        'payment_expires_at' => 'datetime',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function occurrence()
    {
        return $this->belongsTo(EventOccurrence::class, 'occurrence_id');
    }
}
