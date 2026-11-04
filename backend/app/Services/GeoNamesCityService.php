<?php

namespace App\Services;

use App\Support\CountryRules;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Поиск населённых пунктов в GeoNames (searchJSON/getJSON) для «+ Добавить город».
 * Соглашения те же, что у импорта cities:sync-geonames:
 *  - RU/KZ/UZ — название и регион по-русски (запрос с lang=ru);
 *  - остальные страны — ASCII-название (Str::ascii от toponymName), регион латиницей; русское написание
 *    (alternateNames, lang=ru, кириллица) уходит в city_aliases и участвует в поиске.
 */
class GeoNamesCityService
{
    public function enabled(): bool
    {
        return trim((string) config('geonames.username')) !== '';
    }

    /** Кандидаты по запросу (кириллица/латиница) в выбранной стране. Бросает RuntimeException с кодом ошибки GeoNames. */
    public function search(string $query, string $country): array
    {
        $country = strtoupper($country);
        $key = 'geonames_search_v1_' . md5($country . '|' . mb_strtolower($query));

        $raw = Cache::remember($key, 600, function () use ($query, $country) {
            $json = $this->request('searchJSON', [
                'q'            => $query,
                'country'      => $country,
                'featureClass' => 'P',
                'maxRows'      => 10,
                'orderby'      => 'population',
                'style'        => 'FULL',
                'lang'         => $this->langFor($country),
            ]);
            return $json['geonames'] ?? [];
        });

        $items = [];
        foreach ($raw as $g) {
            $c = $this->normalize($g, $country);
            if ($c) {
                $items[] = $c;
            }
        }

        // уже есть в нашей базе?
        $ids = array_column($items, 'geoname_id');
        $existing = $ids ? DB::table('cities')->whereIn('geoname_id', $ids)->pluck('id', 'geoname_id') : collect();
        foreach ($items as &$it) {
            $it['already_city_id'] = $existing[$it['geoname_id']] ?? null;
        }
        unset($it);

        return $items;
    }

    /** Один объект по geonameId (данные берём заново на сервере, клиенту не доверяем). */
    public function fetch(int $geonameId, string $country): ?array
    {
        $country = strtoupper($country);
        $json = $this->request('getJSON', [
            'geonameId' => $geonameId,
            'style'     => 'FULL',
            'lang'      => $this->langFor($country),
        ]);
        if (empty($json['geonameId'])) {
            return null;
        }
        return $this->normalize($json, $country);
    }

    private function langFor(string $country): string
    {
        return in_array($country, CountryRules::CYRILLIC_NAME_COUNTRIES, true) ? 'ru' : 'en';
    }

    /** Приводит ответ GeoNames к нашему формату; null — не населённый пункт / страна не поддерживается / нет пояса. */
    private function normalize(array $g, string $country): ?array
    {
        $cc = strtoupper((string) ($g['countryCode'] ?? ''));
        if ($cc === '' || !CountryRules::isKnown($cc)) {
            return null;
        }
        if (($g['fcl'] ?? 'P') !== 'P') {
            return null;
        }
        $tz = $g['timezone']['timeZoneId'] ?? ($g['timezone'] ?? null);
        if (!is_string($tz) || $tz === '') {
            return null;
        }
        $cyr = in_array($cc, CountryRules::CYRILLIC_NAME_COUNTRIES, true);
        $toponym = (string) ($g['toponymName'] ?? $g['name'] ?? '');
        if ($toponym === '') {
            return null;
        }

        $name   = $cyr ? (string) ($g['name'] ?? $toponym) : Str::ascii($toponym);
        $region = (string) ($g['adminName1'] ?? '');
        $region = $cyr ? $region : Str::ascii($region);

        // русское написание: из alternateNames (lang=ru, кириллица)
        $ru = null;
        foreach (($g['alternateNames'] ?? []) as $alt) {
            if (($alt['lang'] ?? '') === 'ru' && preg_match('/\p{Cyrillic}/u', (string) ($alt['name'] ?? ''))) {
                if ($ru === null || !empty($alt['isPreferredName'])) {
                    $ru = trim((string) $alt['name']);
                }
            }
        }
        if ($cyr) {
            $ru = null; // у RU/KZ/UZ название и так русское
        }

        return [
            'geoname_id' => (int) $g['geonameId'],
            'country_code' => $cc,
            'name'       => trim($name),
            'name_ru'    => $ru,
            'region'     => trim($region) !== '' ? trim($region) : null,
            'lat'        => isset($g['lat']) ? (float) $g['lat'] : null,
            'lon'        => isset($g['lng']) ? (float) $g['lng'] : null,
            'population' => isset($g['population']) ? (int) $g['population'] : null,
            'timezone'   => $tz,
        ];
    }

    private function request(string $endpoint, array $params): array
    {
        if (!$this->enabled()) {
            throw new \RuntimeException('not_configured');
        }
        $params['username'] = config('geonames.username');
        try {
            $res = Http::timeout((int) config('geonames.timeout', 8))
                ->acceptJson()
                ->get(config('geonames.base_url') . '/' . $endpoint, $params);
        } catch (\Throwable $e) {
            throw new \RuntimeException('unavailable');
        }
        $json = $res->json() ?: [];
        // GeoNames отдаёт ошибки логина/лимитов с HTTP 401/403 и телом {"status":{"message","value"}} — читаем тело до проверки кода
        if (isset($json['status']['value'])) {
            // 10 — логин/сервисы не включены; 18/19/20 — лимиты; прочее — недоступно
            $v = (int) $json['status']['value'];
            throw new \RuntimeException(in_array($v, [18, 19, 20], true) ? 'limit' : ($v === 10 ? 'not_configured' : 'unavailable'));
        }
        if (!$res->ok()) {
            throw new \RuntimeException('unavailable');
        }
        return $json;
    }
}
