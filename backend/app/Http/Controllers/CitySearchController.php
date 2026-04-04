<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        $items = DB::table('cities')
            ->select(['id', 'name', 'region', 'country_code', 'population'])
            ->whereIn('country_code', ['RU', 'KZ', 'UZ'])

            // Убираем районы Москвы (оставляем только "Москва")
            // Т.е. region='Moscow' допустим только если name='Москва'
            ->where(function ($w) {
                $w->whereNull('region')
                  ->orWhere('region', '<>', 'Moscow')
                  ->orWhere('name', '=', 'Москва');
            })

            ->where(function ($w) use ($like) {
                $w->where('name', 'ilike', $like)
                  ->orWhere('region', 'ilike', $like)
                  ->orWhere('country_code', 'ilike', $like);
            })

            // “релевантность”: сначала точные совпадения (без регистра), потом популярные, потом алфавит
            ->orderByRaw("CASE WHEN lower(name) = lower(?) THEN 0 ELSE 1 END", [$q])
            ->orderByRaw("population DESC NULLS LAST")
            ->orderBy('country_code')
            ->orderByRaw("region NULLS LAST")
            ->orderBy('name')
            ->limit($limit)
            ->get();

        return response()->json(['items' => $items]);
    }
}