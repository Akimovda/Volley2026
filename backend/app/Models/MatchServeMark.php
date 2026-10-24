<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Ручная отметка первой подачи партии (см. миграцию match_serve_marks). Вне seq очков и undoLast. */
class MatchServeMark extends Model
{
    protected $table = 'match_serve_marks';

    protected $fillable = ['match_id', 'set_number', 'team_id', 'client_request_id', 'recorded_by_user_id', 'recorded_by_link_id', 'recorded_by_name'];
}
