<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Не путать с TeamSubstitution — та про лиговую замену между турами. */
class MatchSetStart extends Model
{
    protected $table = 'match_set_starts';

    protected $fillable = ['match_id','set_number','first_serve_team_id','recorded_by_user_id','recorded_by_link_id','recorded_by_name'];

    public function match(): BelongsTo
    {
        return $this->belongsTo(TournamentMatch::class, 'match_id');
    }
}
