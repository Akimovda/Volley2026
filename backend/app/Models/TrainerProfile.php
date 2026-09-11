<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainerProfile extends Model
{
    protected $fillable = [
        'user_id', 'specialization', 'bio', 'experience_years', 'is_public',
    ];

    protected $casts = [
        'experience_years' => 'integer',
        'is_public'         => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
