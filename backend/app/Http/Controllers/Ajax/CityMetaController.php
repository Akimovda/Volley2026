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

        $city = City::query()->select(['id', 'timezone'])->find($cityId);
        if (!$city) {
            return response()->json(['ok' => false]);
        }

        return response()->json([
            'ok' => true,
            'timezone' => (string) ($city->timezone ?? ''),
        ]);
    }
}
