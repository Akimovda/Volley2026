<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventRoleSlot extends Model
{
    protected $table = 'event_role_slots';

    protected $fillable = [
        'event_id',
        'role',
        'max_slots',
        'taken_slots',
    ];

    protected $casts = [
        'max_slots' => 'integer',
        'taken_slots' => 'integer',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
