<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivitySession;
use App\Services\ActivitySessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ActivityImportController extends Controller
{
    public function __construct(private ActivitySessionService $service) {}

    public function importHealthKit(Request $request): JsonResponse
    {
        $user     = $request->user();
        $imported = 0;
        $skipped  = 0;

        foreach ($request->input('workouts', []) as $workout) {
            $externalId = $workout['workoutId'] ?? null;

            if ($externalId && ActivitySession::where('user_id', $user->id)
                ->where('external_workout_id', $externalId)
                ->exists()) {
                $skipped++;
                continue;
            }

            $startedAt   = Carbon::parse($workout['startDate']);
            $endedAt     = Carbon::parse($workout['endDate']);
            $durationSec = $workout['durationSec'] ?? (int) $endedAt->diffInSeconds($startedAt);
            $kcal        = (float) ($workout['activeEnergyKcal'] ?? 0);
            $steps       = (int) ($workout['steps'] ?? 0);
            $hrSamples   = $workout['hrSamples'] ?? [];

            $bpmValues = array_column($hrSamples, 'bpm');
            $avgHr     = count($bpmValues) ? (int) round(array_sum($bpmValues) / count($bpmValues)) : null;
            $maxHr     = count($bpmValues) ? max($bpmValues) : null;
            $minHr     = count($bpmValues) ? min($bpmValues) : null;

            $session = ActivitySession::create([
                'user_id'             => $user->id,
                'source'              => 'healthkit_import',
                'external_workout_id' => $externalId,
                'status'              => 'completed',
                'started_at'          => $startedAt,
                'ended_at'            => $endedAt,
                'duration_sec'        => $durationSec,
                'calories_kcal'       => $kcal ?: null,
                'calorie_source'      => 'healthkit',
                'steps'               => $steps ?: null,
                'avg_hr'              => $avgHr,
                'max_hr'              => $maxHr,
                'min_hr'              => $minHr,
            ]);

            if (!empty($hrSamples)) {
                $this->service->ingestSamples($session, $hrSamples);
            }

            $imported++;
        }

        return response()->json([
            'imported' => $imported,
            'skipped'  => $skipped,
        ]);
    }
}
