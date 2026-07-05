<?php

namespace App\Http\Controllers\Ajax;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Services\CourtAvailabilityService;
use App\Services\CourtPricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CourtBookingWindowsController extends Controller
{
    public function __invoke(Request $request, Location $location, CourtAvailabilityService $service, CourtPricingService $pricingService)
    {
        $direction = (string) $request->query('direction', '');
        $durationMinutes = (int) $request->query('duration', 0);
        $dateInput = (string) $request->query('date', '');

        if (!in_array($direction, ['classic', 'beach'], true) || $durationMinutes <= 0 || $dateInput === '') {
            return response()->json(['ok' => false, 'courts' => [], 'slots' => []], 422);
        }

        $directionModel = $location->directions()
            ->where('direction', $direction)
            ->where('is_active', true)
            ->with(['courts' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->first();

        if (!$directionModel) {
            return response()->json(['ok' => true, 'courts' => [], 'slots' => []]);
        }

        $tz = $location->effectiveTimezone();
        $date = Carbon::parse($dateInput, $tz);

        if ($date->startOfDay()->lt(Carbon::now($tz)->startOfDay())) {
            $courts = $directionModel->courts->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values();
            return response()->json(['ok' => true, 'is_past' => true, 'courts' => $courts, 'slots' => []]);
        }

        $windows = $service->windowsForDuration($directionModel, $date, $durationMinutes);

        $courts = $directionModel->courts->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values();

        $slotsByCourtId = [];
        foreach ($directionModel->courts as $court) {
            $slots = [];
            foreach (($windows[$court->id] ?? []) as $window) {
                $startMin = $this->timeToMin($window['start']);
                $endMin = $this->timeToMin($window['end']);

                for ($m = $startMin; $m + $durationMinutes <= $endMin; $m += 30) {
                    $slotStart = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
                    $startsAtLocal = Carbon::parse($date->toDateString() . ' ' . $slotStart, $tz);
                    $endsAtLocal = $startsAtLocal->copy()->addMinutes($durationMinutes);
                    $price = $pricingService->calculate(
                        $court,
                        $startsAtLocal->copy()->setTimezone('UTC'),
                        $endsAtLocal->copy()->setTimezone('UTC')
                    );
                    $slots[] = ['start' => $slotStart, 'price' => $price];
                }
            }
            $slotsByCourtId[$court->id] = $slots;
        }

        return response()->json([
            'ok'     => true,
            'courts' => $courts,
            'slots'  => $slotsByCourtId,
        ]);
    }

    private function timeToMin(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', substr($time, 0, 5)));
        return $h * 60 + $m;
    }
}
