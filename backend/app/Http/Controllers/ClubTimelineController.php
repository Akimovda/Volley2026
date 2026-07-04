<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Services\TimelineService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ClubTimelineController extends Controller
{
    public function show(Request $request, Location $location)
    {
        $user = $request->user();
        abort_unless(
            $user && ($user->isAdmin() || (int) $location->owner_id === (int) $user->id),
            403
        );

        $date = Carbon::parse($request->input('date', now()));
        $mode = $request->input('mode', 'day');

        $service = app(TimelineService::class);

        return response()->json(
            $mode === 'week'
                ? $service->week($location, $date->copy()->startOfWeek())
                : $service->day($location, $date)
        );
    }
}
