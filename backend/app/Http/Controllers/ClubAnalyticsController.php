<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Services\ClubAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ClubAnalyticsController extends Controller
{
    public function index(Request $request, ClubAnalyticsService $service)
    {
        $user = $request->user();
        abort_unless($user && ($user->is_club_manager || $user->isAdmin()), 403);

        $locationIds = $user->isAdmin()
            ? Location::whereNotNull('owner_id')->pluck('id')
            : Location::where('owner_id', $user->id)->pluck('id');

        $locations = Location::whereIn('id', $locationIds)->orderBy('name')->get();

        $period = $request->input('period', ClubAnalyticsService::PERIOD_MONTH);
        if (!in_array($period, ClubAnalyticsService::PERIODS, true)) {
            $period = ClubAnalyticsService::PERIOD_MONTH;
        }

        if ($locations->isEmpty()) {
            return view('club.analytics', [
                'locations' => $locations,
                'location' => null,
                'period' => $period,
                'periodStart' => null,
                'periodEnd' => null,
                'prevAnchor' => null,
                'nextAnchor' => null,
                'stats' => null,
            ]);
        }

        $selectedLocationId = (int) $request->input('location_id', $locations->first()->id);
        $location = $locations->firstWhere('id', $selectedLocationId) ?? $locations->first();

        // Границы периода считаются в локальном календаре локации (её effectiveTimezone) —
        // иначе "июль" для клуба в Москве мог бы съехать на несколько часов от реального
        // местного месяца при сравнении с court_bookings.starts_at (хранится в UTC).
        $tz = $location->effectiveTimezone();
        $anchor = $request->filled('anchor')
            ? Carbon::parse($request->input('anchor'), $tz)
            : Carbon::now($tz);

        [$periodStart, $periodEnd] = $service->resolvePeriod($period, $anchor);

        $stats = $service->forLocation($location, $periodStart, $periodEnd);

        $prevAnchor = $service->shiftAnchor($period, $anchor, -1);
        $nextAnchor = $service->shiftAnchor($period, $anchor, 1);

        return view('club.analytics', [
            'locations' => $locations,
            'location' => $location,
            'period' => $period,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
            'prevAnchor' => $prevAnchor,
            'nextAnchor' => $nextAnchor,
            'stats' => $stats,
        ]);
    }
}
