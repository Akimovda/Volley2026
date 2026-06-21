<?php

namespace App\Services;

use App\Models\ActivitySession;
use App\Models\AthleteDevice;
use App\Models\EventOccurrence;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ActivitySessionService
{
    public function __construct(private readonly AthleteProfileService $profileService) {}

    public function start(User $user, ?EventOccurrence $occurrence, ?AthleteDevice $device): ActivitySession
    {
        $direction = null;
        if ($occurrence) {
            $event     = $occurrence->event ?? null;
            $direction = $event?->direction ?? null;
        }

        return ActivitySession::create([
            'user_id'       => $user->id,
            'occurrence_id' => $occurrence?->id,
            'device_id'     => $device?->id,
            'direction'     => $direction,
            'status'        => 'live',
            'started_at'    => now(),
            'samples_count' => 0,
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

    public function finalize(ActivitySession $session): ActivitySession
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

        $session->update([
            'status'        => 'completed',
            'ended_at'      => now(),
            'duration_sec'  => $durationSec,
            'avg_hr'        => $avgBpm,
            'max_hr'        => $maxBpm,
            'min_hr'        => $minBpm === PHP_INT_MAX ? null : $minBpm,
            'time_in_zone'  => $timeInZone,
            'load_score'    => round($loadScore, 2),
            'samples_count' => $count,
        ]);

        $session->refresh();
        return $session;
    }
}
