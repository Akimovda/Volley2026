<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionCouponLog extends Model
{
    protected $fillable = [
        'entity_type', 'entity_id', 'user_id', 'action', 'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public static function write(
        string $entityType,
        int $entityId,
        string $action,
        ?array $payload = null,
        ?int $userId = null
    ): void {
        self::create([
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'user_id'     => $userId ?? auth()->id(),
            'action'      => $action,
            'payload'     => $payload,
        ]);
    }
}
