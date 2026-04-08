<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserLevelVote extends Model
{
    protected $fillable = ['voter_id', 'target_id', 'direction', 'level'];

    public function voter() { return $this->belongsTo(User::class, 'voter_id'); }
    public function target() { return $this->belongsTo(User::class, 'target_id'); }
}
