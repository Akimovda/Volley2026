<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Псевдоним входа: provider+provider_user_id, который больше не принадлежит
 * живой строке users напрямую (после merge — provider id остался только здесь,
 * т.к. primary уже владел своим значением этого поля), но должен продолжать
 * резолвиться в правильного пользователя при повторном входе тем же провайдером.
 */
class AccountLink extends Model
{
    protected $fillable = [
        'user_id',
        'provider',
        'provider_user_id',
        'provider_username',
        'provider_email',
        'source',
        'merged_from_user_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mergedFromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'merged_from_user_id');
    }

    /** Пользователь, в которого сейчас резолвится provider+provider_user_id, если это alias. */
    public static function resolveUser(string $provider, string $providerUserId): ?User
    {
        $link = static::where('provider', $provider)
            ->where('provider_user_id', $providerUserId)
            ->first();

        return $link?->user;
    }

    public static function isTaken(string $provider, string $providerUserId, ?int $exceptUserId = null): bool
    {
        return static::where('provider', $provider)
            ->where('provider_user_id', $providerUserId)
            ->when($exceptUserId !== null, fn ($q) => $q->where('user_id', '!=', $exceptUserId))
            ->exists();
    }
}
