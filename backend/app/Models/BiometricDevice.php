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

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
