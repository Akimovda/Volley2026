<?php

namespace App\Services;

use App\Models\EventOccurrence;
use App\Models\EventRegistration;
use App\Models\Payment;
use App\Models\VolleyballSchool;
use Carbon\CarbonInterface;

/**
 * §6.4: карточка аналитики тренера за период.
 * Все метрики считаются только по ЗАВЕРШИВШИМСЯ occurrences событий организатора
 * школы, где тренер эффективен (§2.1) — будущие/идущие туры в период не попадают
 * в статистику (часы/охват/выручка ещё не "случились").
 */
class TrainerAnalyticsService
{
    public function __construct(
        private TrainerResolverService $resolver,
        private TrainerRateService $rateService,
    ) {
    }

    /**
     * @return array{
     *   hours: float, unique_players: int, retention_pct: ?float, fill_rate_pct: ?float,
     *   sessions_count: int, sessions_without_rate: int,
     *   revenue: ?float, cost: float, margin: ?float, to_pay: float,
     * }
     */
    public function metricsForTrainer(VolleyballSchool $school, int $trainerUserId, CarbonInterface $from, CarbonInterface $to, bool $includeFinance): array
    {
        $tz = $school->effectiveTimezone();

        // $from/$to приходят как локальные (TZ школы) границы периода — переводим
        // в UTC явно, т.к. starts_at хранится в UTC (см. CLAUDE.md).
        $utcFrom = \App\Support\DateTime::parseLocalToUtc($from->format('Y-m-d H:i:s'), $tz);
        $utcTo   = \App\Support\DateTime::parseLocalToUtc($to->format('Y-m-d H:i:s'), $tz);

        $occurrences = EventOccurrence::query()
            ->whereHas('event', fn ($q) => $q->where('organizer_id', $school->organizer_id))
            ->whereBetween('starts_at', [$utcFrom, $utcTo])
            ->where(function ($q) {
                $q->whereNull('is_cancelled')->orWhere('is_cancelled', false);
            })
            ->with(['event:id,organizer_id', 'event.gameSettings:id,event_id,max_players', 'gameSettingsOverride:id,occurrence_id,max_players', 'trainers:id'])
            ->orderBy('starts_at')
            ->get()
            ->filter(fn (EventOccurrence $occ) => $occ->isFinished())
            ->filter(fn (EventOccurrence $occ) => in_array($trainerUserId, $this->resolver->effectiveTrainerIds($occ), true))
            ->values();

        $sessionsCount = $occurrences->count();

        if ($sessionsCount === 0) {
            return [
                'hours' => 0.0, 'unique_players' => 0, 'retention_pct' => null, 'fill_rate_pct' => null,
                'sessions_count' => 0, 'sessions_without_rate' => 0,
                'revenue' => $includeFinance ? 0.0 : null, 'cost' => 0.0,
                'margin' => $includeFinance ? 0.0 : null, 'to_pay' => 0.0,
            ];
        }

        $occurrenceIds = $occurrences->pluck('id')->all();

        $hours = round($occurrences->sum('duration_sec') / 3600, 1);

        $registrations = EventRegistration::whereIn('occurrence_id', $occurrenceIds)
            ->where(function ($q) {
                $q->whereNull('is_cancelled')->orWhere('is_cancelled', false);
            })
            ->get(['user_id', 'occurrence_id']);

        $uniquePlayers = $registrations->pluck('user_id')->unique()->count();

        $retentionPct = null;
        if ($uniquePlayers > 0) {
            $sessionsPerUser = $registrations->groupBy('user_id')->map(fn ($g) => $g->pluck('occurrence_id')->unique()->count());
            $repeat = $sessionsPerUser->filter(fn ($n) => $n >= 2)->count();
            $retentionPct = round($repeat / $uniquePlayers * 100, 1);
        }

        $registeredByOccurrence = $registrations->groupBy('occurrence_id')->map->count();
        $fillRates = [];
        foreach ($occurrences as $occ) {
            $capacity = $occ->gameSettingsOverride?->max_players ?? $occ->event?->gameSettings?->max_players ?? null;
            if ($capacity && $capacity > 0) {
                $registered = $registeredByOccurrence->get($occ->id, 0);
                $fillRates[] = min($registered / $capacity, 1.5); // допускаем овербукинг за счёт резерва, но не бесконечность
            }
        }
        $fillRatePct = $fillRates ? round(array_sum($fillRates) / count($fillRates) * 100, 1) : null;

        $revenue = null;
        if ($includeFinance) {
            $revenue = round(((int) Payment::whereIn('occurrence_id', $occurrenceIds)->where('status', 'paid')->sum('amount_minor')) / 100, 2);
        }

        [$cost, $sessionsWithoutRate] = $this->calculateCost($school, $trainerUserId, $occurrences, $tz);

        $margin = $includeFinance ? round($revenue - $cost, 2) : null;

        return [
            'hours'                  => $hours,
            'unique_players'         => $uniquePlayers,
            'retention_pct'          => $retentionPct,
            'fill_rate_pct'          => $fillRatePct,
            'sessions_count'         => $sessionsCount,
            'sessions_without_rate'  => $sessionsWithoutRate,
            'revenue'                => $revenue,
            'cost'                   => $cost,
            'margin'                 => $margin,
            'to_pay'                 => $cost,
        ];
    }

    /**
     * Стоимость тренера за период (то же самое, что "к выплате").
     * hourly/per_session считаются за каждую сессию; fixed_monthly — не более одного
     * раза за календарный месяц (последняя по дате сессия месяца определяет,
     * какая версия ставки применяется — на случай смены ставки в середине месяца).
     * Сессии без ЛЮБОЙ разрешённой ставки (ни event-, ни school-уровня) НЕ считаются
     * нулём — они исключаются из суммы и попадают в счётчик $sessionsWithoutRate.
     *
     * @return array{0: float, 1: int}
     */
    private function calculateCost(VolleyballSchool $school, int $trainerUserId, \Illuminate\Support\Collection $occurrences, string $tz): array
    {
        $cost = 0.0;
        $withoutRate = 0;
        $fixedMonthly = [];

        foreach ($occurrences as $occ) {
            $resolved = $this->rateService->effectiveRateForEvent($school, $occ->event_id, $trainerUserId, $occ->starts_at);
            if ($resolved === null) {
                $withoutRate++;
                continue;
            }

            switch ($resolved['rate_type']) {
                case 'hourly':
                    $cost += $resolved['rate'] * ((int) $occ->duration_sec / 3600);
                    break;
                case 'per_session':
                    $cost += $resolved['rate'];
                    break;
                case 'fixed_monthly':
                default:
                    $rawUtc = $occ->getRawOriginal('starts_at');
                    $local = \App\Support\DateTime::utcToLocal($rawUtc, $tz);
                    $monthKey = $local ? $local->format('Y-m') : 'unknown';
                    $fixedMonthly[$monthKey] = $resolved['rate']; // последняя по хронологии сессия месяца побеждает
                    break;
            }
        }

        $cost += array_sum($fixedMonthly);

        return [round($cost, 2), $withoutRate];
    }
}
