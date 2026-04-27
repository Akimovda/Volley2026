<?php

namespace App\Support;

use Carbon\Carbon;
use DateTimeInterface;
use DateTimeZone;
use Throwable;

class DateTime
{
    public static function nowUtc(): Carbon
    {
        return Carbon::now('UTC');
    }

    /**
     * Парсит значение как UTC.
     * Принимает string|Carbon|DateTimeInterface|null.
     */
    public static function parseUtc($value): ?Carbon
    {
        if ($value === null || $value === '') return null;

        try {
            if ($value instanceof Carbon) {
                return $value->copy()->setTimezone('UTC');
            }

            if ($value instanceof DateTimeInterface) {
                return Carbon::instance($value)->setTimezone('UTC');
            }

            return Carbon::parse((string)$value, 'UTC')->setTimezone('UTC');
        } catch (Throwable $e) {
            return null;
        }
    }
    public static function effectiveUserTz(?\App\Models\User $user, ?string $fallbackTz = null): string
    {
        $cityTz = $user?->city?->timezone;
        if ($cityTz) return self::userTz($cityTz);

        if ($fallbackTz !== null && trim($fallbackTz) !== '') {
            return self::userTz($fallbackTz);
        }

        return self::userTz(config('app.timezone', 'UTC'));
    }
    /**
     * $local ожидаем вида 'Y-m-d\TH:i' или любой parseable Carbon
     * Возвращаем UTC.
     */
    public static function parseLocalToUtc(string $local, string $tz): Carbon
    {
        $tz = self::userTz($tz);

        try {
            return Carbon::parse($local, $tz)->setTimezone('UTC');
        } catch (Throwable $e) {
            // если tz/строка кривые — пробуем как UTC (лучше чем 500)
            return Carbon::parse($local, 'UTC')->setTimezone('UTC');
        }
    }

    public static function utcToLocal($utcValue, string $tz): ?Carbon
    {
        $c = self::parseUtc($utcValue);
        if (!$c) return null;

        $tz = self::userTz($tz);

        try {
            return $c->setTimezone($tz);
        } catch (Throwable $e) {
            return $c; // UTC
        }
    }

    public static function formatLocal($utcValue, string $tz, string $format = 'd.m.Y H:i'): ?string
    {
        $c = self::utcToLocal($utcValue, $tz);
        return $c ? $c->format($format) : null;
    }

    public static function asDateTimeString(DateTimeInterface $dt): string
    {
        return Carbon::instance($dt)->format('Y-m-d H:i:s');
    }
    public static function utcToUser($utcValue, ?\App\Models\User $user): ?Carbon
    {
        $c = self::parseUtc($utcValue);
        if (!$c) return null;
    
        return $c->setTimezone(self::effectiveUserTz($user));
    }
    /**
     * ✅ Нормализация TZ пользователя (валидируем IANA, иначе fallback).
     */
    public static function userTz(?string $tz): string
    {
        $tz = trim((string)$tz);

        if ($tz !== '' && self::isValidTz($tz)) {
            return $tz;
        }

        $fallback = trim((string)config('event_timezones.default', ''));
        if ($fallback !== '' && self::isValidTz($fallback)) {
            return $fallback;
        }

        return 'UTC';
    }

    /**
     * ✅ Форматирует UTC datetime в TZ пользователя с подписью зоны.
     * Пример: "23.02.2026 · 08:29 MSK (UTC+03:00)"
     */
    public static function fmtUser($utcValue, string $userTz, string $format = 'd.m.Y · H:i'): ?string
    {
        $c = self::parseUtc($utcValue);
        if (!$c) return null;

        $userTz = self::userTz($userTz);

        try {
            $c = $c->setTimezone($userTz);
        } catch (Throwable $e) {
            $c = $c->setTimezone('UTC');
        }

        return $c->format($format) . ' ' . $c->format('T') . ' (UTC' . $c->format('P') . ')';
    }

    private static function isValidTz(string $tz): bool
    {
        try {
            new DateTimeZone($tz);
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }
}