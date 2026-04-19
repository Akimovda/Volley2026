<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EventTeam extends Model
{
    protected $fillable = [
        'event_id',
        'occurrence_id',
        'captain_user_id',
        'name',
        'team_kind',
        'status',
        'invite_code',
        'is_complete',
        'last_checked_at',
        'confirmed_at',
        'payment_status',
        'payment_id',
        'meta',
    ];

    protected $casts = [
        'is_complete' => 'boolean',
        'last_checked_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'meta' => 'array',
    ];
    public function invites(): HasMany
    {
        return $this->hasMany(EventTeamInvite::class, 'event_team_id');
    }
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class, 'occurrence_id');
    }

    public function captain(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captain_user_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(EventTeamMember::class);
    }

    public function confirmedMembers(): HasMany
    {
        return $this->hasMany(EventTeamMember::class)
            ->where('confirmation_status', 'confirmed');
    }

    public function application(): HasOne
    {
        return $this->hasOne(EventTeamApplication::class);
    }

    public function audits(): HasMany
    {
        return $this->hasMany(EventTeamMemberAudit::class);
    }

    /* ── Tournament ── */

    public function tournamentGroups(): BelongsToMany
    {
        return $this->belongsToMany(TournamentGroup::class, 'tournament_group_teams', 'team_id', 'group_id')
                    ->withPivot('seed')
                    ->withTimestamps();
    }

    public function tournamentMatchesHome(): HasMany
    {
        return $this->hasMany(TournamentMatch::class, 'team_home_id');
    }

    public function tournamentMatchesAway(): HasMany
    {
        return $this->hasMany(TournamentMatch::class, 'team_away_id');
    }

    public function tournamentStandings(): HasMany
    {
        return $this->hasMany(TournamentStanding::class, 'team_id');
    }


    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function isPaid(): bool
    {
        return in_array($this->payment_status, ['paid', 'subscription', null]);
    }

}
