<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserRestriction extends Model
{
    protected $fillable = [
        'user_id',
        'scope',      // site|events
        'ends_at',    // null = пожизненно
        'event_ids',  // array|null
        'reason',
        'created_by',
    ];

    protected $casts = [
        'ends_at' => 'datetime',
        'event_ids' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(): bool
    {
        return $this->ends_at === null || $this->ends_at->isFuture();
    }
}
