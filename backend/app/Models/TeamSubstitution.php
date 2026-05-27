<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamSubstitution extends Model
{
    protected $fillable = [
        'league_id',
        'occurrence_id',
        'team_id',
        'original_player_id',
        'substitute_player_id',
        'substitute_source',
        'initiated_by',
        'captain_confirmed_at',
        'substitute_confirmed_at',
        'status',
    ];

    protected $casts = [
        'captain_confirmed_at'   => 'datetime',
        'substitute_confirmed_at' => 'datetime',
    ];

    public function league(): BelongsTo
    {
        return $this->belongsTo(TournamentLeague::class);
    }

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(EventTeam::class);
    }

    public function originalPlayer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'original_player_id');
    }

    public function substitutePlayer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'substitute_player_id');
    }

    public function scopeConfirmed($q)
    {
        return $q->where('status', 'confirmed');
    }

    public function scopePending($q)
    {
        return $q->where('status', 'pending');
    }

    public function scopeForOccurrence($q, int $occurrenceId)
    {
        return $q->where('occurrence_id', $occurrenceId);
    }

    public function isFullyConfirmed(): bool
    {
        return $this->captain_confirmed_at !== null
            && $this->substitute_confirmed_at !== null;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending'   => __('tournaments.awaiting_confirmation'),
            'confirmed' => __('tournaments.substitution_confirmed'),
            'cancelled' => __('tournaments.substitution_cancelled'),
            'applied'   => __('tournaments.substitution_confirmed'),
            'expired'   => __('tournaments.substitution_cancelled'),
            default     => $this->status,
        };
    }
}
