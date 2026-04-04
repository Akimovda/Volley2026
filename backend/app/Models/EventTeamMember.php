<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventTeamMember extends Model
{
    protected $fillable = [
        'event_team_id',
        'user_id',
        'role_code',
        'team_role',
        'position_code',
        'confirmation_status',
        'position_order',
        'invited_by_user_id',
        'joined_at',
        'responded_at',
        'confirmed_at',
        'meta',
    ];

    protected $casts = [
        'position_order' => 'integer',
        'joined_at' => 'datetime',
        'responded_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'meta' => 'array',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(EventTeam::class, 'event_team_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    public function getEffectiveTeamRoleAttribute(): string
    {
        if (!empty($this->team_role)) {
            return $this->team_role;
        }
    
        return match ((string) $this->role_code) {
            'captain' => 'captain',
            'reserve' => 'reserve',
            default => 'player',
        };
    }
    
    public function getEffectivePositionCodeAttribute(): ?string
    {
        if (!empty($this->position_code)) {
            return $this->position_code;
        }
    
        return in_array((string) $this->role_code, ['setter', 'outside', 'opposite', 'middle', 'libero'], true)
            ? (string) $this->role_code
            : null;
    }
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }
}
