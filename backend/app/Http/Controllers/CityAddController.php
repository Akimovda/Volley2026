<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Services\GeoNamesCityService;
use App\Support\CountryRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * «+ Добавить город»: поиск в GeoNames (suggest) и создание города в нашей базе (store).
 * Доступно ролям Админ / Организатор / Staff. Города создаются сразу (по данным GeoNames, не по вводу пользователя).
 */
class CityAddController extends Controller
{
    public static function userCanAdd($user): bool
    {
        return $user && ($user->isAdmin() || $user->isOrganizer() || $user->isStaff());
    }

    public function suggest(Request $request, GeoNamesCityService $geo): JsonResponse
    {
        $user = $request->user();
        if (!self::userCanAdd($user)) {
            return response()->json(['error' => 'forbidden'], 403);
        }
        if (!$geo->enabled()) {
            return response()->json(['error' => 'not_configured', 'items' => []], 200);
        }

        $q = trim(preg_replace('/\s+/u', ' ', (string) $request->query('q', '')));
        $country = strtoupper(trim((string) $request->query('country', '')));
        if (mb_strlen($q) < 2 || mb_strlen($q) > 80) {
            return response()->json(['error' => 'query', 'items' => []], 422);
        }
        if (!in_array($country, CountryRules::SUPPORTED, true)) {
            return response()->json(['error' => 'country_unsupported', 'items' => []], 422);
        }

        try {
            $items = $geo->search($q, $country);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage(), 'items' => []], 200);
        }

        foreach ($items as &$it) {
            $it['country_name'] = $this->countryName($it['country_code']);
        }
        unset($it);

        return response()->json(['items' => $items]);
    }

    public function store(Request $request, GeoNamesCityService $geo): JsonResponse
    {
        $user = $request->user();
        if (!self::userCanAdd($user)) {
            return response()->json(['error' => 'forbidden'], 403);
        }
        $data = $request->validate([
            'geoname_id' => ['required', 'integer', 'min:1'],
            'country'    => ['required', 'string', 'size:2'],
            'query'      => ['nullable', 'string', 'max:120'],
        ]);
        $country = strtoupper($data['country']);
        if (!in_array($country, CountryRules::SUPPORTED, true)) {
            return response()->json(['error' => 'country_unsupported'], 422);
        }

        // уже есть в базе — отдаём существующий
        $existing = City::query()->where('geoname_id', $data['geoname_id'])->first();
        if ($existing) {
            $this->log($user->id, $existing->id, (int) $data['geoname_id'], $country, $data['query'] ?? null, 'exists');
            return response()->json(['city' => $this->payload($existing, null), 'created' => false]);
        }

        // суточный лимит добавлений
        $limit = (int) config('geonames.daily_additions_per_user', 20);
        $today = DB::table('city_additions')->where('user_id', $user->id)->where('status', 'created')
            ->where('created_at', '>=', now()->subDay())->count();
        if ($today >= $limit) {
            return response()->json(['error' => 'daily_limit'], 429);
        }

        try {
            $c = $geo->fetch((int) $data['geoname_id'], $country);
        } catch (\RuntimeException $e) {
            $this->log($user->id, null, (int) $data['geoname_id'], $country, $data['query'] ?? null, 'failed', ['reason' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 502);
        }
        if (!$c) {
            $this->log($user->id, null, (int) $data['geoname_id'], $country, $data['query'] ?? null, 'failed', ['reason' => 'not_a_place']);
            return response()->json(['error' => 'not_found'], 404);
        }

        // тот же город мог быть добавлен ранее вручную/импортом без geoname_id (имя + страна + регион)
        $dup = City::query()->where('country_code', $c['country_code'])->where('name', $c['name'])
            ->where(function ($w) use ($c) {
                $c['region'] === null ? $w->whereNull('region') : $w->where('region', $c['region']);
            })->first();
        if ($dup) {
            $this->log($user->id, $dup->id, $c['geoname_id'], $c['country_code'], $data['query'] ?? null, 'exists');
            return response()->json(['city' => $this->payload($dup, null), 'created' => false]);
        }

        $city = DB::transaction(function () use ($c, $user, $data) {
            // forceCreate: в City::$fillable только name/region/timezone — остальные поля (страна, координаты, geoname_id) иначе отбросились бы
            $city = City::query()->forceCreate([
                'name'         => $c['name'],
                'region'       => $c['region'],
                'country_code' => $c['country_code'],
                'timezone'     => $c['timezone'],
                'lat'          => $c['lat'],
                'lon'          => $c['lon'],
                'population'   => $c['population'],
                'geoname_id'   => $c['geoname_id'],
            ]);
            // служебные поля (могут отсутствовать в $fillable)
            DB::table('cities')->where('id', $city->id)->update(['source' => 'geonames_user', 'created_by' => $user->id]);

            if (!empty($c['name_ru'])) {
                DB::table('city_aliases')->insertOrIgnore([
                    'city_id'    => $city->id,
                    'alias'      => $c['name_ru'],
                    'alias_norm' => mb_strtolower(str_replace(['ё', 'Ё'], 'е', $c['name_ru'])),
                    'locale'     => 'ru',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $this->log($user->id, $city->id, $c['geoname_id'], $c['country_code'], $data['query'] ?? null, 'created');
            return $city;
        });

        return response()->json(['city' => $this->payload($city->fresh(), $c['name_ru']), 'created' => true], 201);
    }

    private function countryName(string $code): string
    {
        $n = trans('countries.' . $code);
        return $n !== 'countries.' . $code ? $n : $code;
    }

    /** Тот же формат, что у /cities/search — чтобы страница могла сразу выбрать город. */
    private function payload($city, ?string $nameAlt): array
    {
        return [
            'id'             => (int) $city->id,
            'name'           => $city->name,
            'region'         => $city->region,
            'region_display' => City::displayRegion($city->name, $city->region),
            'country_code'   => $city->country_code,
            'country_name'   => $this->countryName((string) $city->country_code),
            'timezone'       => $city->timezone,
            'population'     => $city->population,
            'name_alt'       => app()->getLocale() === 'ru' ? $nameAlt : null,
            'level_scope'    => level_terminology_scope_for_region($city->region, $city->country_code),
        ];
    }

    private function log(int $userId, ?int $cityId, int $geonameId, string $country, ?string $query, string $status, array $meta = []): void
    {
        DB::table('city_additions')->insert([
            'user_id'    => $userId,
            'city_id'    => $cityId,
            'geoname_id' => $geonameId,
            'country_code' => $country,
            'query'      => $query ? mb_substr($query, 0, 120) : null,
            'status'     => $status,
            'meta'       => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
