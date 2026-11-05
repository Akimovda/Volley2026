<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Попытка входа/привязки через MAX (см. миграцию 2026_11_05_170000_max_login). */
class MaxLoginAttempt extends Model
{
    public const TTL_MINUTES = 10;
    public const MAX_CODE_TRIES = 3;
    /** Длина токена в ссылке: префикс LGN + 32 символа (у токенов привязок уведомлений — 48, пересечений нет). */
    public const TOKEN_PREFIX = 'LGN';

    protected $guarded = [];

    protected $casts = [
        'expires_at'  => 'datetime',
        'approved_at' => 'datetime',
    ];

    /** @return array{0: self, 1: string} попытка и токен для ссылки (в базе — только sha256 токена) */
    public static function start(string $intent, ?int $userId, ?string $returnTo, ?string $ip, ?string $ua): array
    {
        $secret = self::TOKEN_PREFIX . Str::random(32);

        $attempt = self::create([
            'public_id'   => Str::random(40),
            'secret_hash' => hash('sha256', $secret),
            'code'        => str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT),
            'status'      => 'pending',
            'intent'      => $intent,
            'user_id'     => $userId,
            'return_to'   => $returnTo,
            'ip'          => $ip,
            'user_agent'  => $ua ? mb_substr($ua, 0, 255) : null,
            'expires_at'  => now()->addMinutes(self::TTL_MINUTES),
        ]);

        return [$attempt, $secret];
    }

    public static function findBySecret(string $secret): ?self
    {
        return self::where('secret_hash', hash('sha256', $secret))->first();
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
