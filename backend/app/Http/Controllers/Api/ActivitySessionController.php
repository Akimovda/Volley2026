<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivitySession;
use App\Models\AthleteDevice;
use App\Models\EventOccurrence;
use App\Services\ActivitySessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ActivitySessionController extends Controller
{
    public function __construct(private readonly ActivitySessionService $service) {}

    public function start(Request $request): JsonResponse
    {
        $data = $request->validate([
            'occurrence_id' => ['nullable', 'integer', 'exists:event_occurrences,id'],
            'device_id'     => ['nullable', 'integer', 'exists:athlete_devices,id'],
        ]);

        $occurrence = isset($data['occurrence_id'])
            ? EventOccurrence::find($data['occurrence_id'])
            : null;

        $device = isset($data['device_id'])
            ? AthleteDevice::where('id', $data['device_id'])
                ->where('user_id', $request->user()->id)
                ->firstOrFail()
            : null;

        $session = $this->service->start($request->user(), $occurrence, $device);

        return response()->json(['session_id' => $session->id], 201);
    }

    public function ingestSamples(Request $request, ActivitySession $session): JsonResponse
    {
        if ($session->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'samples'         => ['required', 'array', 'max:5000'],
            'samples.*.t'     => ['required', 'integer', 'min:0'],
            'samples.*.bpm'   => ['required', 'integer', 'min:30', 'max:240'],
        ]);

        $accepted = $this->service->ingestSamples($session, $data['samples']);

        return response()->json(['accepted' => $accepted]);
    }

    public function finalize(Request $request, ActivitySession $session): JsonResponse
    {
        if ($session->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $session = $this->service->finalize($session);

        return response()->json([
            'session_id'    => $session->id,
            'status'        => $session->status,
            'duration_sec'  => $session->duration_sec,
            'avg_hr'        => $session->avg_hr,
            'max_hr'        => $session->max_hr,
            'min_hr'        => $session->min_hr,
            'load_score'    => $session->load_score,
            'samples_count' => $session->samples_count,
            'time_in_zone'  => $session->time_in_zone,
        ]);
    }
}
