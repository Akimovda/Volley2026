<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentTiebreaker extends Model
{
    protected $fillable = [
        'stage_id',
        'group_id',
        'team_a_id',
        'team_b_id',
        'method',
        'match_id',
        'winner_team_id',
        'status',
        'resolved_by_user_id',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function stage(): BelongsTo
    {
        return $this->belongsTo(TournamentStage::class, 'stage_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(TournamentGroup::class, 'group_id');
    }

    public function teamA(): BelongsTo
    {
        return $this->belongsTo(EventTeam::class, 'team_a_id');
    }

    public function teamB(): BelongsTo
    {
        return $this->belongsTo(EventTeam::class, 'team_b_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(EventTeam::class, 'winner_team_id');
    }

    public function tiebreakerMatch(): BelongsTo
    {
        return $this->belongsTo(TournamentMatch::class, 'match_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isResolved(): bool
    {
        return $this->status === 'resolved';
    }

    public function involvesTeam(int $teamId): bool
    {
        return $this->team_a_id === $teamId || $this->team_b_id === $teamId;
    }
}
