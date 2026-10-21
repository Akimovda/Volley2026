<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BroadcastCourtOverride extends Model
{
    public const MODES = ['splash', 'live', 'set_summary', 'match_summary', 'off'];

    protected $fillable = ['court_id', 'stream_urls', 'manual_mode', 'manual_mode_at', 'manual_match_id'];

    protected $casts = ['stream_urls' => 'array', 'manual_mode_at' => 'datetime'];

    public function court(): BelongsTo
    {
        return $this->belongsTo(EventCourt::class, 'court_id');
    }
}
