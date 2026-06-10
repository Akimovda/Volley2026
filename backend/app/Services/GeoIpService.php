<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GeoIpService
{
    private const LOCAL_IPS = ['127.0.0.1', '::1', '::ffff:127.0.0.1'];

    public function getCountryCode(string $ip): ?string
    {
        if (in_array($ip, self::LOCAL_IPS, true) || str_starts_with($ip, '192.168.') || str_starts_with($ip, '10.')) {
            return null;
        }

        return Cache::remember("geoip:{$ip}", 86400, function () use ($ip) {
            try {
                $response = Http::timeout(2)->get("https://api.country.is/{$ip}");
                if ($response->ok()) {
                    return $response->json('country') ?: null;
                }
            } catch (\Exception) {
                // при недоступности API не ограничиваем
            }
            return null;
        });
    }

    public function isRussia(string $ip): bool
    {
        return $this->getCountryCode($ip) === 'RU';
    }
}
