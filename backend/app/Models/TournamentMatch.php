<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentMatch extends Model
{
    protected $table = 'tournament_matches';

    protected $fillable = [
        'stage_id', 'group_id', 'round', 'bracket_position', 'match_number',
        'team_home_id', 'team_away_id', 'court', 'scheduled_at',
        'status', 'winner_team_id',
        'score_home', 'score_away', 'sets_home', 'sets_away',
        'total_points_home', 'total_points_away',
        'next_match_id', 'next_match_slot',
        'loser_next_match_id', 'loser_next_match_slot',
        'scored_by_user_id', 'scored_at',
    ];

    protected $casts = [
        'score_home'        => 'array',
        'score_away'        => 'array',
        'sets_home'         => 'integer',
        'sets_away'         => 'integer',
        'total_points_home' => 'integer',
        'total_points_away' => 'integer',
        'round'             => 'integer',
        'match_number'      => 'integer',
        'scheduled_at'      => 'datetime',
        'scored_at'         => 'datetime',
    ];

    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_LIVE      = 'live';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_FORFEIT   = 'forfeit';

    public function stage(): BelongsTo     { return $this->belongsTo(TournamentStage::class, 'stage_id'); }
    public function group(): BelongsTo     { return $this->belongsTo(TournamentGroup::class, 'group_id'); }
    public function teamHome(): BelongsTo  { return $this->belongsTo(EventTeam::class, 'team_home_id'); }
    public function teamAway(): BelongsTo  { return $this->belongsTo(EventTeam::class, 'team_away_id'); }
    public function winner(): BelongsTo    { return $this->belongsTo(EventTeam::class, 'winner_team_id'); }
    public function nextMatch(): BelongsTo { return $this->belongsTo(self::class, 'next_match_id'); }
    public function loserNextMatch(): BelongsTo { return $this->belongsTo(self::class, 'loser_next_match_id'); }
    public function scoredBy(): BelongsTo  { return $this->belongsTo(User::class, 'scored_by_user_id'); }

    public function isCompleted(): bool { return $this->status === self::STATUS_COMPLETED; }
    public function isLive(): bool      { return $this->status === self::STATUS_LIVE; }
    public function isScheduled(): bool { return $this->status === self::STATUS_SCHEDULED; }

    public function hasTeams(): bool
    {
        return $this->team_home_id !== null && $this->team_away_id !== null;
    }

    /** Счёт: "2:1 (25:23, 21:25, 15:11)" */
    public function scoreFormatted(): ?string
    {
        if (! $this->isCompleted()) return null;

        $sets = sprintf('%d:%d', $this->sets_home, $this->sets_away);
        $details = [];
        $home = $this->score_home ?? [];
        $away = $this->score_away ?? [];
        foreach ($home as $i => $h) {
            $details[] = $h . ':' . ($away[$i] ?? 0);
        }
        return $details ? $sets . ' (' . implode(', ', $details) . ')' : $sets;
    }

    public function loserId(): ?int
    {
        if (! $this->winner_team_id) return null;
        return $this->winner_team_id === $this->team_home_id
            ? $this->team_away_id
            : $this->team_home_id;
    }
}
