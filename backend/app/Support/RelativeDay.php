<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * «Регистрация откроется сегодня в 18:00 / завтра в 18:00 / 07 ноября в 18:00».
 * День сравниваем в часовом поясе самой даты (она уже переведена в пояс, в котором показывается пользователю).
 */
class RelativeDay
{
    /**
     * @param string|null $otherFormat формат для остальных дней (по умолчанию — events.dt_format_date_at_time)
     */
    public static function dateAtTime(CarbonInterface $dt, ?string $otherFormat = null, ?string $locale = null): string
    {
        $locale = $locale ?: app()->getLocale();
        $now    = now($dt->getTimezone());
        $time   = $dt->format('H:i');

        if ($dt->isSameDay($now)) {
            return __('events.relative_today_at', ['time' => $time], $locale);
        }
        if ($dt->isSameDay($now->copy()->addDay())) {
            return __('events.relative_tomorrow_at', ['time' => $time], $locale);
        }

        $format = $otherFormat ?? __('events.dt_format_date_at_time', [], $locale);

        return $dt->copy()->locale($locale)->translatedFormat($format);
    }
}
