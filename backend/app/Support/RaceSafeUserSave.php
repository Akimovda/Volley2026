<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Создание пользователя при входе через OAuth/мессенджер устойчиво к двойному callback (двойной тап, повтор запроса):
 * если параллельный запрос уже создал такого пользователя, вторая вставка падает на уникальном индексе —
 * вместо ошибки 500 берём уже созданного по идентификатору провайдера.
 */
class RaceSafeUserSave
{
    /** Возвращает сохранённого пользователя; ->wasRecentlyCreated = false, если пользователя создал параллельный запрос. */
    public static function save(User $user, string $providerColumn, string|int $providerId): User
    {
        try {
            $user->save();
            return $user;
        } catch (UniqueConstraintViolationException $e) {
            $existing = User::where($providerColumn, $providerId)->first();
            if (!$existing) {
                throw $e;
            }
            return $existing;
        }
    }
}
