<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserConsent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'type',
        'document_version',
        'locale',
        'accepted_at',
        'created_at',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'created_at'  => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
