<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolTrainer extends Model
{
    public const STATUS_PENDING   = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_DECLINED  = 'declined';
    public const STATUS_REMOVED   = 'removed';

    protected $fillable = [
        'school_id', 'user_id', 'invited_by_user_id', 'status',
        'can_manage_schedule', 'can_manage_registrations', 'can_view_analytics',
        'invited_at', 'confirmed_at', 'removed_at',
    ];

    protected $casts = [
        'can_manage_schedule'      => 'boolean',
        'can_manage_registrations' => 'boolean',
        'can_view_analytics'       => 'boolean',
        'invited_at'                => 'datetime',
        'confirmed_at'              => 'datetime',
        'removed_at'                => 'datetime',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(VolleyballSchool::class, 'school_id');
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }
}
