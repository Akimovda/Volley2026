<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Номер на футболке: уровень «событие + игрок» (см. миграцию event_jersey_numbers). */
class EventJerseyNumber extends Model
{
    protected $fillable = ['event_id', 'user_id', 'number', 'updated_by_user_id', 'updated_by_link_id'];

    protected $casts = ['number' => 'integer'];
}
