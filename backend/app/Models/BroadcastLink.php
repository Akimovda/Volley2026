<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BroadcastLink extends Model
{
    public const ROLE_OVERLAY   = 'overlay';
    public const ROLE_SCORER    = 'scorer';
    public const ROLE_SECRETARY = 'secretary';
    public const ROLE_STATS     = 'stats';
    public const ROLES = [self::ROLE_OVERLAY, self::ROLE_SCORER, self::ROLE_SECRETARY, self::ROLE_STATS];

    protected $fillable = [
        'event_id', 'occurrence_id', 'court_id', 'role', 'token', 'label',
        'created_by_user_id', 'expires_at', 'revoked_at', 'last_used_at',
        'assigned_user_id', 'last_actor_name', 'last_actor_user_id', 'last_actor_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'last_used_at' => 'datetime',
        'last_actor_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(EventCourt::class, 'court_id');
    }

    /** Приглашённый пользователь (ссылка ведущего «под аккаунт»); NULL — анонимная ссылка. */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function lastActorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_actor_user_id');
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
