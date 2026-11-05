<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Support\CountryRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CitySearchController extends Controller
{
    public function search(Request $request)
    {
        $q = trim((string)$request->query('q', ''));
        $limit = (int)$request->query('limit', 30);
        $limit = max(1, min(50, $limit));

        if (mb_strlen($q) < 2) {
            return response()->json(['items' => []]);
        }

        // Нормализация пробелов
        $q = preg_replace('/\s+/u', ' ', $q);

        // ILIKE в Postgres — ок
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';

        // Поиск по-русски и без диакритики: русские названия городов (city_aliases, alias_norm — нижний регистр, ё→е),
        // названия стран ru/en (+синонимы), unaccent для латиницы (Kusadasi → Kuşadası). Кириллические запросы по name/region
        // идут как раньше (без unaccent), чтобы выдача для RU/KZ/UZ не менялась.
        $hasCyr = (bool) preg_match('/\p{Cyrillic}/u', $q);
        $qNorm = CountryRules::searchNorm($q);
        $likeNorm = '%' . str_replace(['%', '_'], ['\%', '\_'], $qNorm) . '%';
        $hasAliases = Cache::remember('city_search_aliases_v1', 300, fn () => \App\Support\SchemaCache::hasTable('city_aliases') && \App\Support\SchemaCache::hasColumn('cities', 'is_featured'));
        $useUnaccent = !$hasCyr && Cache::remember('city_search_unaccent_v1', 300, fn () => (int) DB::selectOne("select count(*) c from pg_extension where extname = 'unaccent'")->c > 0);
        $nameCodes = CountryRules::codesMatchingName($q);

        // Необязательный фильтр по стране (селектор страны в профиле); без него — все поддерживаемые страны,
        // но RU/KZ/UZ в выдаче выше остальных — для привычных запросов результат прежний.
        $country = strtoupper(trim((string) $request->query('country', '')));
        $supported = CountryRules::supported();
        $countries = in_array($country, $supported, true) ? [$country] : $supported;

        $query = DB::table('cities')
            ->select(['id', 'name', 'region', 'country_code', 'population']);
        if ($hasAliases) {
            $query->selectRaw("(select a.alias from city_aliases a where a.city_id = cities.id and a.locale = 'ru' order by a.id limit 1) as name_alt");
        }
        $items = $query
            ->whereIn('country_code', $countries)

            // Убираем районы Москвы (оставляем только "Москва")
            // Т.е. region='Moscow' допустим только если name='Москва'
            ->where(function ($w) {
                $w->whereNull('region')
                  ->orWhere('region', '<>', 'Moscow')
                  ->orWhere('name', '=', 'Москва');
            })

            ->where(function ($w) use ($like, $likeNorm, $useUnaccent, $hasAliases, $nameCodes) {
                if ($useUnaccent) {
                    $w->whereRaw('unaccent(name) ilike unaccent(?)', [$like])
                      ->orWhereRaw('unaccent(coalesce(region, \'\')) ilike unaccent(?)', [$like]);
                } else {
                    $w->where('name', 'ilike', $like)
                      ->orWhere('region', 'ilike', $like);
                }
                $w->orWhere('country_code', 'ilike', $like);
                if ($hasAliases) {
                    $w->orWhereExists(function ($sub) use ($likeNorm) {
                        $sub->selectRaw('1')->from('city_aliases as a')
                            ->whereColumn('a.city_id', 'cities.id')
                            ->where('a.alias_norm', 'like', $likeNorm);
                    });
                }
                if ($nameCodes) {
                    $w->orWhereIn('country_code', $nameCodes);
                }
            })

            // “релевантность”: сначала точные совпадения (имя или русский алиас), потом RU/KZ/UZ, потом популярные курорты, потом население
            ->orderByRaw($hasAliases
                ? "CASE WHEN lower(name) = lower(?) OR EXISTS (SELECT 1 FROM city_aliases a WHERE a.city_id = cities.id AND a.alias_norm = ?) THEN 0 ELSE 1 END"
                : "CASE WHEN lower(name) = lower(?) THEN 0 ELSE 1 END", $hasAliases ? [$q, $qNorm] : [$q])
            ->orderByRaw("CASE WHEN country_code IN ('RU','KZ','UZ') THEN 0 ELSE 1 END")
            ->when($hasAliases, fn ($qb) => $qb->orderByRaw('is_featured DESC'))
            ->orderByRaw("population DESC NULLS LAST")
            ->orderBy('country_code')
            ->orderByRaw("region NULLS LAST")
            ->orderBy('name')
            ->limit($limit)
            ->get();

        // Для городов федерального значения (Москва, СПб, Севастополь) region совпадает
        // с name — отдаём region_display, чтобы клиентский JS не дублировал город в подписи.
        $items->each(function ($item) {
            $item->region_display = City::displayRegion($item->name, $item->region);
            // Подписи для интерфейса: страна словами и русское написание города (только в русской локали)
            $item->country_name = CountryRules::countryName((string) $item->country_code);
            if (app()->getLocale() !== 'ru' || !property_exists($item, 'name_alt')) {
                $item->name_alt = null;
            }
            // Терминология уровней по региону города (Санкт-Петербург и Ленинградская область — 'spb'): на /profile/complete
            // названия уровней должны меняться сразу при выборе города, не дожидаясь сохранения профиля.
            $item->level_scope = level_terminology_scope_for_region($item->region, $item->country_code);
        });

        return response()->json(['items' => $items]);
    }
}