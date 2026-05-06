<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentTiebreakerSet extends Model
{
    protected $fillable = [
        'stage_id',
        'group_id',
        'team_ids',
        'team_ids_key',
        'method',
        'match_settings',
        'resolved_order',
        'status',
        'resolved_by_user_id',
        'resolved_at',
    ];

    protected $casts = [
        'team_ids'       => 'array',
        'match_settings' => 'array',
        'resolved_order' => 'array',
        'resolved_at'    => 'datetime',
    ];

    public function stage(): BelongsTo
    {
        return $this->belongsTo(TournamentStage::class, 'stage_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(TournamentGroup::class, 'group_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isResolved(): bool
    {
        return $this->status === 'resolved';
    }

    public static function buildKey(array $teamIds): string
    {
        $ids = array_map('intval', $teamIds);
        sort($ids);
        return implode('-', $ids);
    }
}
