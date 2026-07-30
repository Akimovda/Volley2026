<?php

namespace App\Http\Controllers\Ajax;

use App\Http\Controllers\Controller;
use App\Models\City;
use Illuminate\Http\Request;

class CityMetaController extends Controller
{
    public function __invoke(Request $request)
    {
        $cityId = (int) $request->query('city_id', 0);

        if ($cityId <= 0) {
            return response()->json(['ok' => false]);
        }

        $city = City::query()->select(['id', 'timezone', 'region'])->find($cityId);
        if (!$city) {
            return response()->json(['ok' => false]);
        }

        return response()->json([
            'ok' => true,
            'timezone' => (string) ($city->timezone ?? ''),
            // Сырое region (НЕ City::displayRegion()/region_display — та схлопывает
            // регион в null, если совпадает с именем города, что ломает определение
            // терминологии уровня именно для Санкт-Петербурга: region у него дословно
            // равно name). level_terminology_scope_for_region() на фронте сравнивает
            // с "Санкт-Петербург"/"Ленинградская область" напрямую — нужен настоящий region.
            'region' => (string) ($city->region ?? ''),
        ]);
    }
}
