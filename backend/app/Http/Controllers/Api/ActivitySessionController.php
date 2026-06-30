<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivitySession;
use App\Models\AthleteDevice;
use App\Models\EventOccurrence;
use App\Services\ActivitySessionService;
use App\Services\AthleteProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ActivitySessionController extends Controller
{
    public function __construct(
        private readonly ActivitySessionService $service,
        private readonly AthleteProfileService $profileService,
    ) {}

    public function start(Request $request): JsonResponse
    {
        if (!$request->user()->hasHealthConsent()) {
            return response()->json([
                'error'           => 'consent_required',
                'consent_version' => config('activity.consent_version'),
            ], 403);
        }

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

        $jumpHeightCoeff = $this->profileService->effectiveJumpCoeff($request->user(), $device);

        return response()->json([
            'session_id'        => $session->id,
            'jump_height_coeff' => $jumpHeightCoeff,
        ], 201);
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

        $validated = $request->validate([
            'active_energy_kcal'   => ['nullable', 'numeric', 'min:0', 'max:5000'],
            'steps'                => ['nullable', 'integer', 'min:0'],
            'expected_jump_count'  => ['nullable', 'integer', 'min:0'],
        ]);

        $activeEnergyKcal = isset($validated['active_energy_kcal']) && $validated['active_energy_kcal'] > 0
            ? (float) $validated['active_energy_kcal']
            : null;

        $session->steps = (int) ($validated['steps'] ?? 0);

        $expectedJumps = (int) ($validated['expected_jump_count'] ?? 0);
        if ($expectedJumps > 0) {
            $session->jump_count_expected = $expectedJumps;
        }

        $session   = $this->service->finalize($session, $activeEnergyKcal);

        if ($expectedJumps > 0) {
            $actualJumps = $session->jump_count ?? 0;
            $mismatch    = $expectedJumps - $actualJumps;
            $session->jump_count_mismatch = $mismatch;
            $session->saveQuietly();

            if ($mismatch > 0) {
                Log::warning('[Activity] Jump count mismatch', [
                    'session_id' => $session->id,
                    'expected'   => $expectedJumps,
                    'actual'     => $actualJumps,
                    'missing'    => $mismatch,
                ]);
            }
        }
        $jumpTrend = $this->service->heightTrend($session);

        return response()->json([
            'session_id'           => $session->id,
            'status'               => $session->status,
            'duration_sec'         => $session->duration_sec,
            'avg_hr'               => $session->avg_hr,
            'max_hr'               => $session->max_hr,
            'min_hr'               => $session->min_hr,
            'load_score'           => $session->load_score,
            'calories_kcal'        => $session->calories_kcal,
            'calorie_source'       => $session->calorie_source,
            'samples_count'        => $session->samples_count,
            'time_in_zone'         => $session->time_in_zone,
            'tracked_capabilities' => $session->tracked_capabilities ?? ['hr'],
            'direction'            => $session->direction,
            'jump_count'           => $session->jump_count,
            'jump_avg_height_cm'   => $session->jump_avg_height_cm,
            'jump_max_height_cm'   => $session->jump_max_height_cm,
            'jump_trend'           => $jumpTrend,
        ]);
    }
}
