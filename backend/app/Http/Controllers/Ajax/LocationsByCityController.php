<?php

namespace App\Http\Controllers\Ajax;

use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Http\Request;

class LocationsByCityController extends Controller
{
    public function __invoke(Request $request)
    {
        $cityId = (int) $request->query('city_id', 0);

        if ($cityId <= 0) {
            return response()->json(['ok' => true, 'items' => []]);
        }

        $items = Location::query()
            ->where('city_id', $cityId)
            ->orderBy('name')
            ->get()
            ->map(function ($loc) {
                // подстрой под свою media-логику (как у тебя в Blade)
                $thumb = method_exists($loc, 'getFirstMediaUrl')
                    ? ($loc->getFirstMediaUrl('photos', 'thumb') ?: $loc->getFirstMediaUrl('photos'))
                    : '';

                return [
                    'id'      => (int) $loc->id,
                    'name'    => (string) ($loc->name ?? ''),
                    'address' => (string) ($loc->address ?? ''),
                    'lat'     => $loc->lat !== null ? (string) $loc->lat : '',
                    'lng'     => $loc->lng !== null ? (string) $loc->lng : '',
                    'short'   => (string) ($loc->short_text ?? ''),
                    'thumb'   => (string) ($thumb ?? ''),
                ];
            })
            ->values();

        return response()->json(['ok' => true, 'items' => $items]);
    }
}
