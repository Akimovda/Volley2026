<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionHistory extends Model
{
    protected $table = 'promotion_history';

    protected $fillable = [
        'season_id',
        'occurrence_id',
        'round_number',
        'league_team_id',
        'user_id',
        'team_id',
        'from_division_id',
        'to_division_id',
        'from_league_id',
        'to_league_id',
        'action',
        'status',
        'initiated_by',
        'notes',
    ];

    protected $casts = [
        'round_number' => 'integer',
    ];

    // Типы действий
    public const ACTION_PROMOTED_UPPER    = 'promoted_to_upper';
    public const ACTION_PROMOTED_PARENT   = 'promoted_to_parent';
    public const ACTION_RELEGATED_LOWER   = 'relegated_to_lower';
    public const ACTION_RELEGATED_FEEDER  = 'relegated_to_feeder';
    public const ACTION_RELEGATED_RESERVE = 'relegated_to_reserve';
    public const ACTION_ENTERED_QUEUE     = 'entered_from_queue';
    public const ACTION_ENTERED_FEEDER    = 'entered_from_feeder';
    public const ACTION_MANUAL_MOVE       = 'manual_move';
    public const ACTION_DECLINED          = 'declined_transfer';

    // Статусы
    public const STATUS_PENDING    = 'pending_confirmation';
    public const STATUS_COMPLETED  = 'completed';
    public const STATUS_DECLINED   = 'declined';
    public const STATUS_EXPIRED    = 'expired';

    // Инициаторы
    public const INITIATED_SYSTEM    = 'system';
    public const INITIATED_ORGANIZER = 'organizer';
    public const INITIATED_ADMIN     = 'admin';
    public const INITIATED_USER      = 'user';

    /* ---------- relations ---------- */

    public function season(): BelongsTo
    {
        return $this->belongsTo(TournamentSeason::class, 'season_id');
    }

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class, 'occurrence_id');
    }

    public function leagueTeam(): BelongsTo
    {
        return $this->belongsTo(TournamentLeagueTeam::class, 'league_team_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(EventTeam::class, 'team_id');
    }

    public function fromDivision(): BelongsTo
    {
        return $this->belongsTo(TournamentLeague::class, 'from_division_id');
    }

    public function toDivision(): BelongsTo
    {
        return $this->belongsTo(TournamentLeague::class, 'to_division_id');
    }

    public function fromLeague(): BelongsTo
    {
        return $this->belongsTo(League::class, 'from_league_id');
    }

    public function toLeague(): BelongsTo
    {
        return $this->belongsTo(League::class, 'to_league_id');
    }
}
