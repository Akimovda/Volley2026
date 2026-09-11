<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolTrainerRate extends Model
{
    public const TYPE_HOURLY        = 'hourly';
    public const TYPE_PER_SESSION   = 'per_session';
    public const TYPE_FIXED_MONTHLY = 'fixed_monthly';

    protected $fillable = [
        'school_id', 'user_id', 'rate_type', 'rate', 'effective_from', 'created_by_user_id',
    ];

    protected $casts = [
        'rate'           => 'float',
        'effective_from' => 'datetime',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(VolleyballSchool::class, 'school_id');
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
