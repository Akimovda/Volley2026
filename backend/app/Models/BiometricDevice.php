<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Устройство с включённым Face ID / Touch ID; в базе — только sha256-хеш токена. */
class BiometricDevice extends Model
{
    public const MAX_PER_USER = 10;

    protected $guarded = [];

    protected $casts = ['last_used_at' => 'datetime'];

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /** Отключает устройства (все или указанные) вместе с их API-токенами Sanctum. */
    public static function revokeFor(int $userId, ?array $deviceIds = null): void
    {
        $q = static::where('user_id', $userId);
        if ($deviceIds !== null) {
            $q->whereIn('id', $deviceIds);
        }
        $ids = $q->pluck('id');
        if ($ids->isEmpty()) {
            return;
        }
        $names = $ids->map(fn ($id) => 'biometric:' . $id)->all();
        if ($deviceIds === null) {
            $names[] = 'biometric'; // токены прежней схемы без привязки к устройству
        }
        \Illuminate\Support\Facades\DB::table('personal_access_tokens')
            ->where('tokenable_type', User::class)->where('tokenable_id', $userId)
            ->whereIn('name', $names)->delete();
        static::whereIn('id', $ids)->delete();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
