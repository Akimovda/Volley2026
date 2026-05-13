<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserTeamMember extends Model
{
    protected $fillable = ['user_team_id', 'user_id', 'role_code', 'position_code'];

    public function team()
    {
        return $this->belongsTo(UserTeam::class, 'user_team_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
