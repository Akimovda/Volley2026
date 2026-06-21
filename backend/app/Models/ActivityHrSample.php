<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityHrSample extends Model
{
    public $timestamps = false;

    protected $fillable = ['session_id', 't_offset_sec', 'bpm'];

    protected $casts = [
        't_offset_sec' => 'integer',
        'bpm'          => 'integer',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(ActivitySession::class, 'session_id');
    }
}
