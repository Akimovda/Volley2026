<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserTeam extends Model
{
    protected $fillable = ['user_id', 'name', 'direction', 'subtype'];

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function members()
    {
        return $this->hasMany(UserTeamMember::class);
    }

    public function captainMember()
    {
        return $this->hasOne(UserTeamMember::class)->where('role_code', 'captain');
    }
}
