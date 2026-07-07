<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityJumpEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['session_id', 't_offset_ms', 'height_cm', 'type'];

    protected $casts = [
        'height_cm'   => 'decimal:1',
        't_offset_ms' => 'integer',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(ActivitySession::class, 'session_id');
    }
}
