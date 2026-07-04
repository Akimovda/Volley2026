<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchRallyEvent extends Model
{
    protected $table = 'match_rally_events';

    public const ACTION_ACE                  = 'ace';
    public const ACTION_KILL                 = 'kill';
    public const ACTION_BLOCK                = 'block';
    public const ACTION_OPP_SERVE_ERROR      = 'opp_serve_error';
    public const ACTION_OPP_ATTACK_ERROR     = 'opp_attack_error';
    public const ACTION_OPP_BLOCK_ERROR      = 'opp_block_error';
    public const ACTION_OPP_RECEPTION_ERROR  = 'opp_reception_error';
    public const ACTION_UNATTRIBUTED         = 'unattributed';

    public const SELF_ACTIONS = [
        self::ACTION_ACE,
        self::ACTION_KILL,
        self::ACTION_BLOCK,
    ];

    public const OPP_ERROR_ACTIONS = [
        self::ACTION_OPP_SERVE_ERROR,
        self::ACTION_OPP_ATTACK_ERROR,
        self::ACTION_OPP_BLOCK_ERROR,
        self::ACTION_OPP_RECEPTION_ERROR,
    ];

    public const ALL_ACTIONS = [
        self::ACTION_ACE,
        self::ACTION_KILL,
        self::ACTION_BLOCK,
        self::ACTION_OPP_SERVE_ERROR,
        self::ACTION_OPP_ATTACK_ERROR,
        self::ACTION_OPP_BLOCK_ERROR,
        self::ACTION_OPP_RECEPTION_ERROR,
        self::ACTION_UNATTRIBUTED,
    ];

    /** action_type => поле в match_player_stats/STAT_FIELDS. unattributed сознательно отсутствует. */
    public const ACTION_STAT_FIELD = [
        self::ACTION_ACE                 => 'aces',
        self::ACTION_KILL                => 'kills',
        self::ACTION_BLOCK               => 'blocks',
        self::ACTION_OPP_SERVE_ERROR     => 'serve_errors',
        self::ACTION_OPP_ATTACK_ERROR    => 'attack_errors',
        self::ACTION_OPP_BLOCK_ERROR     => 'block_errors',
        self::ACTION_OPP_RECEPTION_ERROR => 'reception_errors',
    ];

    protected $fillable = [
        'match_id', 'set_number', 'team_id', 'team_point_number', 'action_type',
        'player_id', 'stat_team_id', 'dig_user_id', 'assist_user_id', 'recorded_by_user_id',
    ];

    protected $casts = [
        'set_number'        => 'integer',
        'team_point_number' => 'integer',
    ];

    public function match(): BelongsTo
    {
        return $this->belongsTo(TournamentMatch::class, 'match_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(EventTeam::class, 'team_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player_id');
    }

    public function statTeam(): BelongsTo
    {
        return $this->belongsTo(EventTeam::class, 'stat_team_id');
    }

    public function digUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dig_user_id');
    }

    public function assistUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assist_user_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
