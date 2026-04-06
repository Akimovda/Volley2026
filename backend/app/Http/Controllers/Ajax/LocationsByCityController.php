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
        $active = (bool) $request->query('active', false);
    
        $query = Location::query()->orderBy('name');
    
        if ($cityId > 0) {
            $query->where('city_id', $cityId);
        }
    
        if ($active) {
            $now = now('UTC');
            $query->whereExists(function ($sub) use ($now) {
                $sub->selectRaw('1')
                    ->from('event_occurrences as eo')
                    ->join('events as e', 'e.id', '=', 'eo.event_id')
                    ->where(function ($w) {
                        $w->whereColumn('eo.location_id', 'locations.id')
                          ->orWhereColumn('e.location_id', 'locations.id');
                    })
                    ->where('eo.starts_at', '>', $now)
                    ->whereNull('eo.cancelled_at');
            });
        }
    
        if ($cityId <= 0 && !$active) {
            return response()->json(['ok' => true, 'items' => []]);
        }
    
        $items = $query->get()->map(function ($loc) {
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
        })->values();
    
        return response()->json(['ok' => true, 'items' => $items]);
    }
}
