<?php

namespace App\Services;

use App\Models\ActivityJumpEvent;
use App\Models\ActivitySession;
use App\Models\AthleteDevice;
use App\Models\EventOccurrence;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ActivitySessionService
{
    public function __construct(
        private readonly AthleteProfileService $profileService,
        private readonly ActivityCalorieService $calorieService,
    ) {}

    public function start(User $user, ?EventOccurrence $occurrence, ?AthleteDevice $device): ActivitySession
    {
        $direction = null;
        if ($occurrence) {
            $event     = $occurrence->event ?? null;
            $direction = $event?->direction ?? null;
        }

        $capabilities = $device?->capabilities() ?? config('activity.default_capabilities', ['hr']);

        return ActivitySession::create([
            'user_id'              => $user->id,
            'occurrence_id'        => $occurrence?->id,
            'device_id'            => $device?->id,
            'direction'            => $direction,
            'status'               => 'live',
            'started_at'           => now(),
            'samples_count'        => 0,
            'jump_count'           => 0,
            'tracked_capabilities' => $capabilities,
        ]);
    }

    /**
     * Идемпотентный батчевый приём сэмплов.
     * $samples = [['t' => int, 'bpm' => int], ...]
     * Возвращает кол-во фактически вставленных (не дублей).
     */
    public function ingestSamples(ActivitySession $session, array $samples): int
    {
        if (empty($samples)) {
            return 0;
        }

        $rows = array_map(fn($s) => [
            'session_id'   => $session->id,
            't_offset_sec' => (int) $s['t'],
            'bpm'          => (int) $s['bpm'],
        ], $samples);

        // insertOrIgnore генерирует INSERT ... ON CONFLICT DO NOTHING в PostgreSQL
        $countBefore = DB::table('activity_hr_samples')
            ->where('session_id', $session->id)
            ->count();

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('activity_hr_samples')->insertOrIgnore($chunk);
        }

        $countAfter = DB::table('activity_hr_samples')
            ->where('session_id', $session->id)
            ->count();

        $accepted = $countAfter - $countBefore;

        if ($accepted > 0) {
            DB::table('activity_sessions')
                ->where('id', $session->id)
                ->increment('samples_count', $accepted);
        }

        return $accepted;
    }

    /**
     * Идемпотентный батчевый приём прыжков.
     * $jumps = [['t' => int, 'height_cm' => float|null, 'type' => string|null], ...]
     */
    public function ingestJumps(ActivitySession $session, array $jumps): int
    {
        if (empty($jumps)) {
            return 0;
        }

        $rows = array_map(fn($j) => [
            'session_id'   => $session->id,
            't_offset_sec' => (int) $j['t'],
            'height_cm'    => isset($j['height_cm']) ? round((float) $j['height_cm'], 1) : null,
            'type'         => $j['type'] ?? null,
        ], $jumps);

        $countBefore = DB::table('activity_jump_events')
            ->where('session_id', $session->id)
            ->count();

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('activity_jump_events')->insertOrIgnore($chunk);
        }

        $countAfter = DB::table('activity_jump_events')
            ->where('session_id', $session->id)
            ->count();

        return $countAfter - $countBefore;
    }

    /**
     * Тренд высоты прыжков относительно предыдущих 5 jumps-сессий.
     * Возвращает ['avg_prev'=>float,'delta'=>float,'label'=>string] или ['first'=>true].
     */
    public function heightTrend(ActivitySession $session): array
    {
        if ($session->jump_avg_height_cm === null) {
            return ['first' => true];
        }

        $prevSessions = ActivitySession::where('user_id', $session->user_id)
            ->where('id', '!=', $session->id)
            ->where('status', 'completed')
            ->whereNotNull('jump_avg_height_cm')
            ->when($session->direction, fn($q) => $q->where('direction', $session->direction))
            ->whereRaw("tracked_capabilities::jsonb @> '\"jumps\"'")
            ->orderByDesc('started_at')
            ->limit(5)
            ->pluck('jump_avg_height_cm');

        if ($prevSessions->isEmpty()) {
            return ['first' => true];
        }

        $avgPrev = round($prevSessions->avg(), 1);
        $delta   = round((float) $session->jump_avg_height_cm - $avgPrev, 1);

        return [
            'avg_prev' => $avgPrev,
            'delta'    => $delta,
            'label'    => $delta >= 0 ? 'higher' : 'lower',
        ];
    }

    public function finalize(ActivitySession $session, ?float $activeEnergyKcal = null): ActivitySession
    {
        $samples = DB::table('activity_hr_samples')
            ->where('session_id', $session->id)
            ->orderBy('t_offset_sec')
            ->get(['t_offset_sec', 'bpm']);

        if ($samples->isEmpty()) {
            $session->update([
                'status'   => 'completed',
                'ended_at' => now(),
            ]);
            $session->refresh();
            return $session;
        }

        // Загружаем user с профилем
        $user = $session->user()->with('athleteProfile')->first();

        $totalBpm = 0;
        $maxBpm   = 0;
        $minBpm   = PHP_INT_MAX;
        $timeInZone = ['z1' => 0, 'z2' => 0, 'z3' => 0, 'z4' => 0, 'z5' => 0];

        $prevOffset = null;
        foreach ($samples as $s) {
            $bpm    = (int) $s->bpm;
            $offset = (int) $s->t_offset_sec;

            $totalBpm += $bpm;
            if ($bpm > $maxBpm) $maxBpm = $bpm;
            if ($bpm < $minBpm) $minBpm = $bpm;

            // каждый сэмпл представляет 1 секунду интервала
            $zone = $this->profileService->zoneForBpm($user, $bpm);
            if ($zone >= 1 && $zone <= 5) {
                $timeInZone["z$zone"] += 1;
            }

            $prevOffset = $offset;
        }

        $count       = $samples->count();
        $avgBpm      = (int) round($totalBpm / $count);
        $durationSec = $prevOffset + 1; // от 0 до last offset включительно

        // load_score = (z1*1 + z2*2 + z3*3 + z4*4 + z5*5) / 60
        $loadScore = (
            $timeInZone['z1'] * 1 +
            $timeInZone['z2'] * 2 +
            $timeInZone['z3'] * 3 +
            $timeInZone['z4'] * 4 +
            $timeInZone['z5'] * 5
        ) / 60.0;

        // calories: (a) healthkit measured — priority; (b) Keytel estimated — fallback
        $caloriesKcal  = null;
        $calorieSource = null;

        if ($activeEnergyKcal !== null && $activeEnergyKcal > 0) {
            // (a) Apple Watch / HealthKit measured value
            $caloriesKcal  = round($activeEnergyKcal, 1);
            $calorieSource = 'healthkit';
        } else {
            // (b) Keytel formula (requires weight, birth_date, gender)
            $profile  = $user->athleteProfile;
            $weightKg = $profile?->weight_kg ? (float) $profile->weight_kg : null;
            if ($weightKg !== null && $user->birth_date && $user->gender) {
                $age       = (int) $user->birth_date->diffInYears(now());
                $gender    = $user->gender; // 'm' or 'f'
                $totalKcal = 0.0;
                foreach ($samples as $s) {
                    $totalKcal += $this->calorieService->keytelKcalPerMin((int) $s->bpm, $weightKg, $age, $gender) / 60.0;
                }
                $caloriesKcal  = round($totalKcal, 1);
                $calorieSource = 'keytel';
            }
            // (c) neither → both remain null
        }

        // jump aggregates
        $jumpAgg = DB::table('activity_jump_events')
            ->where('session_id', $session->id)
            ->selectRaw('COUNT(*) as cnt, AVG(height_cm) as avg_h, MAX(height_cm) as max_h')
            ->first();

        $jumpCount      = (int) ($jumpAgg->cnt ?? 0);
        $jumpAvgHeight  = $jumpAgg->avg_h !== null ? round((float) $jumpAgg->avg_h, 1) : null;
        $jumpMaxHeight  = $jumpAgg->max_h !== null ? round((float) $jumpAgg->max_h, 1) : null;

        $session->update([
            'status'              => 'completed',
            'ended_at'            => now(),
            'duration_sec'        => $durationSec,
            'avg_hr'              => $avgBpm,
            'max_hr'              => $maxBpm,
            'min_hr'              => $minBpm === PHP_INT_MAX ? null : $minBpm,
            'time_in_zone'        => $timeInZone,
            'load_score'          => round($loadScore, 2),
            'calories_kcal'       => $caloriesKcal,
            'calorie_source'      => $calorieSource,
            'samples_count'       => $count,
            'jump_count'          => $jumpCount,
            'jump_avg_height_cm'  => $jumpAvgHeight,
            'jump_max_height_cm'  => $jumpMaxHeight,
        ]);

        $session->refresh();
        return $session;
    }
}
