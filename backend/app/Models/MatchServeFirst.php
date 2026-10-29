<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Кто подаёт первым у пары в партии (пляжка 2×2). См. миграцию match_serve_firsts. */
class MatchServeFirst extends Model
{
    protected $table = 'match_serve_firsts';

    protected $fillable = ['match_id', 'set_number', 'team_id', 'first_server_user_id', 'client_request_id', 'recorded_by_user_id', 'recorded_by_link_id', 'recorded_by_name'];
}
