<?php

namespace App\Services;

use App\Models\ActivityJumpEvent;
use App\Models\ActivitySession;
use App\Models\AthleteDevice;
use App\Models\EventOccurrence;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ActivitySessionService
{
    // Санити-границы для клиентских started_at/ended_at: не раньше выпуска фичи трекинга
    // и не позже "сейчас + запас на рассинхрон часов устройства".
    private const CLIENT_TS_MIN_YEAR = 2020;
    private const CLIENT_TS_MAX_FUTURE_HOURS = 1;

    public function __construct(
        private readonly AthleteProfileService $profileService,
        private readonly ActivityCalorieService $calorieService,
    ) {}

    /**
     * @param float|null $startedAtTs Unix timestamp (секунды) реального начала тренировки
     *                                на устройстве. Используется только при создании НОВОЙ
     *                                сессии — если сессия уже существует (идемпотентность по
     *                                client_uuid), started_at первого запроса не трогаем.
     */
    public function start(User $user, ?EventOccurrence $occurrence, ?AthleteDevice $device, ?string $clientUuid = null, ?float $startedAtTs = null): ActivitySession
    {
        // Идемпотентность: часы повторяют доставку (sendMessage/transferUserInfo/HTTP retry)
        // с одним и тем же client_uuid — возвращаем уже созданную сессию, не плодим дубли.
        // started_at НЕ обновляем — она зафиксирована первым запросом; повторный (например,
        // после финализации на устройстве) не должен сдвигать время старта тренировки.
        if ($clientUuid !== null) {
            $existing = ActivitySession::where('user_id', $user->id)
                ->where('client_uuid', $clientUuid)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        $direction = null;
        if ($occurrence) {
            $event     = $occurrence->event ?? null;
            $direction = $event?->direction ?? null;
        }

        $capabilities = $device?->capabilities() ?? config('activity.default_capabilities', ['hr']);

        try {
            return ActivitySession::create([
                'user_id'              => $user->id,
                'occurrence_id'        => $occurrence?->id,
                'device_id'            => $device?->id,
                'direction'            => $direction,
                'status'               => 'live',
                'started_at'           => $this->resolveStartedAt($startedAtTs),
                'samples_count'        => 0,
                'jump_count'           => 0,
                'tracked_capabilities' => $capabilities,
                'client_uuid'          => $clientUuid,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Гонка: два одновременных retry прошли проверку выше до insert — ловим уникальный индекс.
            if ($clientUuid !== null && str_contains($e->getMessage(), 'activity_sessions_user_id_client_uuid_unique')) {
                return ActivitySession::where('user_id', $user->id)
                    ->where('client_uuid', $clientUuid)
                    ->firstOrFail();
            }

            throw $e;
        }
    }

    private function resolveStartedAt(?float $startedAtTs): Carbon
    {
        return $this->sanitizeClientTimestamp($startedAtTs, 'started_at');
    }

    private function resolveEndedAt(?float $endedAtTs): Carbon
    {
        return $this->sanitizeClientTimestamp($endedAtTs, 'ended_at');
    }

    /**
     * КРИТИЧНО: только createFromTimestamp(), никогда Carbon::parse() для голого числа —
     * parse('1720512938') интерпретирует строку не как epoch и даёт год 2938 (проверено
     * экспериментально), т.к. без префикса "@" это не распознаётся как unix timestamp.
     */
    private function sanitizeClientTimestamp(?float $ts, string $field): Carbon
    {
        if ($ts === null) {
            return now();
        }

        $candidate = Carbon::createFromTimestamp($ts);
        $min       = Carbon::create(self::CLIENT_TS_MIN_YEAR, 1, 1);
        $max       = now()->addHours(self::CLIENT_TS_MAX_FUTURE_HOURS);

        if ($candidate->lt($min) || $candidate->gt($max)) {
            Log::warning("[Activity] Некорректный {$field} от клиента, использован now()", [
                $field        => $ts,
                'candidate'   => $candidate->toDateTimeString(),
            ]);

            return now();
        }

        return $candidate;
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

            // Сессия уже финализирована, но досланные (retry) сэмплы должны попасть в
            // видимые пользователю агрегаты — иначе они останутся в БД, но не на экране.
            if ($session->status === 'completed') {
                $this->recomputeAggregates($session);
            }
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
            'session_id'  => $session->id,
            't_offset_ms' => (int) $j['t'],
            'height_cm'   => isset($j['height_cm']) ? round((float) $j['height_cm'], 1) : null,
            'type'        => $j['type'] ?? null,
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

        $accepted = $countAfter - $countBefore;

        // См. комментарий в ingestSamples(): досланные после finalize() прыжки должны
        // обновить jump_count/высоты на сессии, иначе они видны только в сырой таблице.
        if ($accepted > 0 && $session->status === 'completed') {
            $this->recomputeAggregates($session);
        }

        return $accepted;
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

    public function finalize(ActivitySession $session, ?float $activeEnergyKcal = null, ?float $endedAtTs = null): ActivitySession
    {
        $endedAt = $this->resolveEndedAt($endedAtTs);
        // timestamp-разница вместо diffInSeconds() (тот по умолчанию absolute=false и может дать отрицательное число)
        $durationSec = max(0, $endedAt->timestamp - $session->started_at->timestamp);

        // Читаем сэмплы напрямую из БД в момент finalize — клиент досылает их ДО этого вызова,
        // кэша между ingestSamples() и finalize() нет, поэтому калории всегда считаются по актуальным данным.
        $samples = DB::table('activity_hr_samples')
            ->where('session_id', $session->id)
            ->orderBy('t_offset_sec')
            ->get(['t_offset_sec', 'bpm']);

        // calories: (a) healthkit measured — priority; (b) Keytel estimated — fallback; (c) нет сэмплов/данных — null
        $caloriesKcal  = null;
        $calorieSource = null;

        if ($activeEnergyKcal !== null && $activeEnergyKcal > 0) {
            // (a) Apple Watch / HealthKit measured value
            $caloriesKcal  = round($activeEnergyKcal, 1);
            $calorieSource = 'healthkit';
        } elseif ($samples->isNotEmpty()) {
            // (b) Keytel formula (requires weight, birth_date, gender)
            $user     = $session->user()->with('athleteProfile')->first();
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
        }

        $session->update([
            'status'         => 'completed',
            'ended_at'       => $endedAt,
            'duration_sec'   => $durationSec,
            'calories_kcal'  => $caloriesKcal,
            'calorie_source' => $calorieSource,
            'finalized_at'   => now(),
        ]);

        $this->recomputeAggregates($session);

        $session->refresh();
        return $session;
    }

    /**
     * Пересчитывает HR/jump-агрегаты сессии из сырых таблиц (activity_hr_samples,
     * activity_jump_events). Вызывается и из finalize() (первичный расчёт), и из
     * ingestSamples()/ingestJumps() (пересчёт при данных, досланных retry-путём уже
     * ПОСЛЕ финализации — иначе они лягут в сырые таблицы, но пользователь их не увидит).
     *
     * НЕ трогает: ended_at, duration_sec, calories_kcal, calorie_source, status,
     * started_at, finalized_at — эти поля описывают саму тренировку/факт финализации,
     * а не производные метрики, и не должны сбрасываться при простом довесе данных.
     */
    private function recomputeAggregates(ActivitySession $session): void
    {
        // Прыжки считаются независимо от наличия HR-сэмплов (устройство может уметь только jumps)
        $jumpAgg = DB::table('activity_jump_events')
            ->where('session_id', $session->id)
            ->selectRaw('COUNT(*) as cnt, AVG(height_cm) as avg_h, MAX(height_cm) as max_h')
            ->first();

        $update = [
            'jump_count'         => (int) ($jumpAgg->cnt ?? 0),
            'jump_avg_height_cm' => $jumpAgg->avg_h !== null ? round((float) $jumpAgg->avg_h, 1) : null,
            'jump_max_height_cm' => $jumpAgg->max_h !== null ? round((float) $jumpAgg->max_h, 1) : null,
        ];

        $samples = DB::table('activity_hr_samples')
            ->where('session_id', $session->id)
            ->orderBy('t_offset_sec')
            ->get(['t_offset_sec', 'bpm']);

        if ($samples->isEmpty()) {
            $update['samples_count'] = 0;
        } else {
            $user = $session->user()->with('athleteProfile')->first();

            $totalBpm   = 0;
            $maxBpm     = 0;
            $minBpm     = PHP_INT_MAX;
            $timeInZone = ['z1' => 0, 'z2' => 0, 'z3' => 0, 'z4' => 0, 'z5' => 0];

            foreach ($samples as $s) {
                $bpm = (int) $s->bpm;

                $totalBpm += $bpm;
                if ($bpm > $maxBpm) $maxBpm = $bpm;
                if ($bpm < $minBpm) $minBpm = $bpm;

                // каждый сэмпл представляет 1 секунду интервала
                $zone = $this->profileService->zoneForBpm($user, $bpm);
                if ($zone >= 1 && $zone <= 5) {
                    $timeInZone["z$zone"] += 1;
                }
            }

            $count  = $samples->count();
            $avgBpm = (int) round($totalBpm / $count);

            // load_score = (z1*1 + z2*2 + z3*3 + z4*4 + z5*5) / 60
            $loadScore = (
                $timeInZone['z1'] * 1 +
                $timeInZone['z2'] * 2 +
                $timeInZone['z3'] * 3 +
                $timeInZone['z4'] * 4 +
                $timeInZone['z5'] * 5
            ) / 60.0;

            $update['avg_hr']        = $avgBpm;
            $update['max_hr']        = $maxBpm;
            $update['min_hr']        = $minBpm === PHP_INT_MAX ? null : $minBpm;
            $update['time_in_zone']  = $timeInZone;
            $update['load_score']    = round($loadScore, 2);
            $update['samples_count'] = $count;
        }

        $session->update($update);
        $session->refresh();
    }
}
