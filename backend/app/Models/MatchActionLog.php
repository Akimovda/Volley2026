<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Журнал отмен в поочковом вводе: физически удалённая строка хранится копией в payload. */
class MatchActionLog extends Model
{
    protected $table = 'match_action_log';

    public $timestamps = false;

    protected $fillable = [
        'match_id', 'set_number', 'action', 'payload',
        'recorded_by_user_id', 'recorded_by_link_id', 'recorded_by_name',
    ];

    protected $casts = ['payload' => 'array'];
}
