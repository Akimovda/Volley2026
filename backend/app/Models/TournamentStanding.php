<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentStanding extends Model
{
    protected $fillable = [
        'stage_id', 'group_id', 'team_id',
        'played', 'wins', 'losses', 'draws',
        'sets_won', 'sets_lost', 'points_scored', 'points_conceded',
        'rating_points', 'rank',
    ];

    protected $casts = [
        'played' => 'integer', 'wins' => 'integer', 'losses' => 'integer',
        'draws' => 'integer', 'sets_won' => 'integer', 'sets_lost' => 'integer',
        'points_scored' => 'integer', 'points_conceded' => 'integer',
        'rating_points' => 'integer', 'rank' => 'integer',
    ];

    public function stage(): BelongsTo { return $this->belongsTo(TournamentStage::class, 'stage_id'); }
    public function group(): BelongsTo { return $this->belongsTo(TournamentGroup::class, 'group_id'); }
    public function team(): BelongsTo  { return $this->belongsTo(EventTeam::class, 'team_id'); }

    public function matchWinRate(): float
    {
        $total = $this->wins + $this->losses + $this->draws;
        return $total > 0 ? round($this->wins / $total * 100, 2) : 0;
    }

    public function setWinRate(): float
    {
        $total = $this->sets_won + $this->sets_lost;
        return $total > 0 ? round($this->sets_won / $total * 100, 2) : 0;
    }

    public function pointDiff(): int { return $this->points_scored - $this->points_conceded; }
    public function setDiff(): int   { return $this->sets_won - $this->sets_lost; }
}
