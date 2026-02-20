<?php

namespace App\Support;

use Carbon\Carbon;
use DateTimeInterface;

class DateTime
{
    public static function nowUtc(): Carbon
    {
        return Carbon::now('UTC');
    }

    public static function parseUtc($value): ?Carbon
    {
        if ($value === null || $value === '') return null;
        return Carbon::parse($value, 'UTC')->setTimezone('UTC');
    }

    public static function parseLocalToUtc(string $local, string $tz): Carbon
    {
        // $local ожидаем вида 'Y-m-d\TH:i' или любой parseable Carbon
        return Carbon::parse($local, $tz)->setTimezone('UTC');
    }

    public static function utcToLocal($utcValue, string $tz): ?Carbon
    {
        $c = self::parseUtc($utcValue);
        return $c ? $c->setTimezone($tz) : null;
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
}
