<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TournamentLeague extends Model
{
    protected $fillable = [
        'season_id',
        'name',
        'level',
        'sort_order',
        'max_teams',
        'config',
    ];

    protected $casts = [
        'config' => 'array',
    ];

    /* ---------- relations ---------- */

    public function season(): BelongsTo
    {
        return $this->belongsTo(TournamentSeason::class, 'season_id');
    }

    public function leagueTeams(): HasMany
    {
        return $this->hasMany(TournamentLeagueTeam::class, 'league_id');
    }

    public function activeTeams(): HasMany
    {
        return $this->hasMany(TournamentLeagueTeam::class, 'league_id')
                    ->where('status', 'active');
    }

    public function reserveTeams(): HasMany
    {
        return $this->hasMany(TournamentLeagueTeam::class, 'league_id')
                    ->where('status', 'reserve')
                    ->orderBy('reserve_position');
    }

    public function seasonEvents(): HasMany
    {
        return $this->hasMany(TournamentSeasonEvent::class, 'league_id')->orderBy('round_number');
    }

    public function stats(): HasMany
    {
        return $this->hasMany(TournamentSeasonStats::class, 'league_id');
    }

    /* ---------- helpers ---------- */

    public function cfg(string $key, mixed $default = null): mixed
    {
        return data_get($this->config, $key, $default);
    }

    public function promoteCount(): int
    {
        return (int) $this->cfg('promote_count', 0);
    }

    public function relegateCount(): int
    {
        return (int) $this->cfg('relegate_count', 0);
    }

    public function eliminateCount(): int
    {
        return (int) $this->cfg('eliminate_count', 0);
    }

    public function promoteTo(): ?string
    {
        return $this->cfg('promote_to');
    }

    public function hasCapacity(): bool
    {
        if (!$this->max_teams) {
            return true;
        }
        return $this->activeTeams()->count() < $this->max_teams;
    }

    public function nextReservePosition(): int
    {
        return ($this->reserveTeams()->max('reserve_position') ?? 0) + 1;
    }
}
