<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPlayLike extends Model
{
    protected $fillable = ['liker_id', 'target_id'];

    public function liker() { return $this->belongsTo(User::class, 'liker_id'); }
    public function target() { return $this->belongsTo(User::class, 'target_id'); }
}
