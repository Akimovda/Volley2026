<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TournamentGroup extends Model
{
    protected $fillable = [
        'stage_id',
        'name',
        'sort_order',
        'courts',
    ];

    protected $casts = [
        'courts' => 'array',
    ];

    public function stage(): BelongsTo
    {
        return $this->belongsTo(TournamentStage::class, 'stage_id');
    }

    public function groupTeams(): HasMany
    {
        return $this->hasMany(TournamentGroupTeam::class, 'group_id')->orderBy('seed');
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(EventTeam::class, 'tournament_group_teams', 'group_id', 'team_id')
                    ->withPivot('seed')
                    ->orderByPivot('seed');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(TournamentMatch::class, 'group_id');
    }

    public function standings(): HasMany
    {
        return $this->hasMany(TournamentStanding::class, 'group_id')->orderBy('rank');
    }
}
