<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KingBeachStanding extends Model
{
    protected $table = 'king_beach_standings';

    protected $fillable = [
        'stage_id',
        'group_id',
        'user_id',
        'total_points',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
