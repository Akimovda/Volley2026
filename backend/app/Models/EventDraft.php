<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventDraft extends Model
{
    protected $fillable = ['user_id', 'step', 'payload'];

    protected $casts = [
        'payload' => 'array',
        'step'    => 'integer',
    ];
}
