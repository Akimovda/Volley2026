<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventOccurrence extends Model
{
    protected $fillable = [
        'event_id','starts_at','ends_at','timezone',
        'is_cancelled','cancelled_at','uniq_key',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_cancelled' => 'boolean',
        'cancelled_at' => 'datetime',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function registrations()
    {
        return $this->hasMany(EventRegistration::class, 'occurrence_id');
    }
}
