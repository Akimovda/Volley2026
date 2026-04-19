<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentStanding extends Model
{
    protected $fillable = [
        'stage_id',
        'group_id',
        'team_id',
        'played',
        'wins',
        'losses',
        'draws',
        'sets_won',
        'sets_lost',
        'points_scored',
        'points_conceded',
        'rating_points',
        'rank',
    ];

    public function stage(): BelongsTo
    {
        return $this->belongsTo(TournamentStage::class, 'stage_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(TournamentGroup::class, 'group_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(EventTeam::class, 'team_id');
    }

    public function matchWinRate(): float
    {
        if ($this->played === 0) {
            return 0;
        }
        return round($this->wins / $this->played * 100, 2);
    }

    public function setWinRate(): float
    {
        $total = $this->sets_won + $this->sets_lost;
        if ($total === 0) {
            return 0;
        }
        return round($this->sets_won / $total * 100, 2);
    }

    public function pointDiff(): int
    {
        return $this->points_scored - $this->points_conceded;
    }

    public function setDiff(): int
    {
        return $this->sets_won - $this->sets_lost;
    }
}
