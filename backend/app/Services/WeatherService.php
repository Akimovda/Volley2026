<?php

namespace App\Services;

use App\Models\EventOccurrence;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Погода для мероприятий на улице (OpenWeather, бесплатный /data/2.5/forecast:
 * 5 дней, шаг 3 часа). Прогноз кешируется ПО ЛОКАЦИИ; в шаблонах читается
 * только кеш (без сетевых запросов), прогревает его команда weather:refresh.
 */
class WeatherService
{
    public const CACHE_TTL_HOURS = 6;
    /** Максимальный сдвиг ближайшего слота прогноза от старта, сек (шаг 3ч → ≤1.5ч). */
    private const MAX_SLOT_GAP = 5400;

    public static function cacheKey(int $locationId): string
    {
        return 'weather:owm:loc:' . $locationId;
    }

    /** Скачать прогноз и положить в кеш. false — не удалось (старый кеш остаётся). */
    public function refreshLocation(int $locationId, float $lat, float $lng): bool
    {
        $key = config('services.openweather.key');
        if (!$key) {
            return false;
        }

        try {
            $resp = Http::timeout(8)->get('https://api.openweathermap.org/data/2.5/forecast', [
                'lat'   => $lat,
                'lon'   => $lng,
                'units' => 'metric',
                'appid' => $key,
            ]);
        } catch (\Throwable $e) {
            Log::warning('weather.fetch_failed', ['location_id' => $locationId, 'error' => $e->getMessage()]);
            return false;
        }

        if (!$resp->ok()) {
            Log::warning('weather.fetch_bad_status', ['location_id' => $locationId, 'status' => $resp->status()]);
            return false;
        }

        $slots = [];
        foreach ((array) $resp->json('list', []) as $row) {
            $slots[] = [
                'dt'   => (int) ($row['dt'] ?? 0),
                'temp' => isset($row['main']['temp']) ? (float) $row['main']['temp'] : null,
                'pop'  => isset($row['pop']) ? (float) $row['pop'] : 0.0,
                'id'   => (int) ($row['weather'][0]['id'] ?? 800),
                'night' => str_ends_with((string) ($row['weather'][0]['icon'] ?? ''), 'n'),
            ];
        }

        if (!$slots) {
            return false;
        }

        Cache::put(self::cacheKey($locationId), $slots, now()->addHours(self::CACHE_TTL_HOURS));
        return true;
    }

    /**
     * ['temp' => '+14°', 'pop' => 40, 'icon' => '🌧'] или null.
     * Только чтение кеша — безопасно вызывать из карточек списка.
     */
    public function forOccurrence(EventOccurrence $occ): ?array
    {
        $event = $occ->event;
        if (!$event || !$event->is_outdoor || !$occ->location_id || !$occ->starts_at) {
            return null;
        }
        if ($occ->is_cancelled) {
            return null;
        }

        $slots = Cache::get(self::cacheKey((int) $occ->location_id));
        if (!is_array($slots) || !$slots) {
            return null;
        }

        $ts = $occ->starts_at->timestamp;
        $best = null;
        $bestGap = PHP_INT_MAX;
        foreach ($slots as $s) {
            $gap = abs($s['dt'] - $ts);
            if ($gap < $bestGap) {
                $bestGap = $gap;
                $best = $s;
            }
        }

        if (!$best || $bestGap > self::MAX_SLOT_GAP || $best['temp'] === null) {
            return null;
        }

        $t = (int) round($best['temp']);
        return [
            'temp' => ($t > 0 ? '+' : '') . $t . '°',
            'pop'  => (int) round($best['pop'] * 100),
            'icon' => self::icon($best['id'], (bool) $best['night']),
        ];
    }

    /** Иконка для вывода в blade: луна — SVG (наследует цвет текста), остальные — эмодзи. */
    public static function iconHtml(string $icon): \Illuminate\Support\HtmlString
    {
        if ($icon === '🌙') {
            return new \Illuminate\Support\HtmlString('<svg class="weather-moon-svg" viewBox="0 0 24 24" width="1em" height="1em" fill="currentColor" aria-hidden="true" style="vertical-align:-0.125em"><path d="M21 12.79A9 9 0 1 1 11.21 3a7 7 0 0 0 9.79 9.79z"/></svg>');
        }
        return new \Illuminate\Support\HtmlString(e($icon));
    }

    public static function icon(int $id, bool $night = false): string
    {
        return match (true) {
            $id >= 200 && $id < 300 => '⛈',
            $id >= 300 && $id < 400 => '🌦',
            $id >= 500 && $id < 600 => '🌧',
            $id >= 600 && $id < 700 => '❄️',
            $id >= 700 && $id < 800 => '🌫',
            $id === 800             => $night ? '🌙' : '☀️',
            $id === 801             => $night ? '☁️' : '🌤',
            $id === 802             => '⛅',
            default                 => '☁️',
        };
    }
}
