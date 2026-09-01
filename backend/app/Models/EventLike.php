<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventLike extends Model
{
    public $timestamps = false;

    protected $fillable = ['event_id', 'occurrence_id', 'user_id', 'created_at'];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function occurrence()
    {
        return $this->belongsTo(EventOccurrence::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
