<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Не путать с TeamSubstitution — та про лиговую замену между турами. */
class MatchLineupEvent extends Model
{
    protected $table = 'match_lineup_events';

    public const TYPE_SUBSTITUTION = 'substitution';
    public const TYPE_LIBERO_IN    = 'libero_in';
    public const TYPE_LIBERO_OUT   = 'libero_out';
    // Ручные правки расстановки (блок B). rotate: position = знаковая дельта поворота ('+1'/'-1'), без игроков;
    // swap: out_user_id и in_user_id — двое игроков, меняющихся местами. Без миграций: используются существующие колонки.
    public const TYPE_ROTATE       = 'rotate';
    public const TYPE_SWAP         = 'swap';

    protected $fillable = ['match_id','set_number','team_id','type','out_user_id','in_user_id','position','zone','seq','score_home','score_away','recorded_by_user_id','recorded_by_link_id','recorded_by_name','client_request_id'];

    public function match(): BelongsTo
    {
        return $this->belongsTo(TournamentMatch::class, 'match_id');
    }
}
