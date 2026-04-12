<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfileVisit extends Model
{
    public $timestamps = false;

    protected $fillable = ['profile_user_id', 'visitor_user_id', 'visited_at'];

    protected $casts = ['visited_at' => 'datetime'];

    public function profileUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'profile_user_id');
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'visitor_user_id');
    }
}
