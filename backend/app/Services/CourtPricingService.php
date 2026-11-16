<?php

namespace App\Services;

use App\Models\CourtPriceRule;
use App\Models\LocationCourt;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CourtPricingService
{
    /**
     * Стоимость брони: интервал разбивается по получасам, каждый получас
     * оценивается по правилу с максимальным priority (при равенстве —
     * court-specific > direction-wide, day-specific > all-days, time-window >
     * all-day), суммируется. Если правил нет вообще для направления/корта —
     * цена null (бронь без стоимости).
     */
    /**
     * $startsAt/$endsAt приходят в UTC (конвенция court_bookings/events) — для
     * сопоставления с day_of_week/time-window правил приводим момент каждого
     * получаса к таймзоне локации корта, иначе прайм-тайм правила сравниваются
     * со смещённым (UTC) временем суток и никогда не совпадают.
     */
    public function calculate(LocationCourt $court, Carbon $startsAt, Carbon $endsAt): ?float
    {
        $direction = $court->relationLoaded('direction') ? $court->direction : $court->direction()->first();
        if (!$direction) {
            return null;
        }

        $location = $direction->relationLoaded('location') ? $direction->location : $direction->location()->first();
        $tz = $location?->effectiveTimezone() ?: 'Europe/Moscow';

        $rules = CourtPriceRule::where('direction_id', $direction->id)
            ->where(function ($q) use ($court) {
                $q->whereNull('court_id')->orWhere('court_id', $court->id);
            })
            ->get();

        if ($rules->isEmpty()) {
            return null;
        }

        $total = 0.0;
        $cursor = $startsAt->copy();
        while ($cursor->lt($endsAt)) {
            $chunkEnd = $cursor->copy()->addMinutes(30);
            if ($chunkEnd->gt($endsAt)) {
                $chunkEnd = $endsAt->copy();
            }
            $chunkMinutes = $cursor->diffInMinutes($chunkEnd);

            $localMoment = $cursor->copy()->setTimezone($tz);
            $rule = $this->bestRuleFor($rules, $court, $localMoment);
            if ($rule) {
                $total += (float) $rule->price_per_hour * ($chunkMinutes / 60);
            }

            $cursor = $chunkEnd;
        }

        return round($total, 2);
    }

    /**
     * Ставка (₽/час) по времени суток для корта в дату — сжатая по равным подряд идущим отрезкам:
     * [[fromMin, toMin, rate|null], ...] с шагом 5 минут в границах [fromMin, toMin). Правила и приоритеты те же,
     * что в calculate(), поэтому виджет считает цену слота (по получасам) ровно так же, как сервер при брони.
     */
    public function ratesForDay(LocationCourt $court, Carbon $localDate, int $fromMin, int $toMin, ?Collection $preloadedRules = null): array
    {
        $direction = $court->relationLoaded('direction') ? $court->direction : $court->direction()->first();
        if (!$direction) {
            return [];
        }

        $location = $direction->relationLoaded('location') ? $direction->location : $direction->location()->first();
        $tz = $location?->effectiveTimezone() ?: 'Europe/Moscow';

        $rules = $preloadedRules !== null
            ? $preloadedRules->filter(fn ($r) => $r->court_id === null || (int) $r->court_id === (int) $court->id)
            : CourtPriceRule::where('direction_id', $direction->id)
                ->where(function ($q) use ($court) {
                    $q->whereNull('court_id')->orWhere('court_id', $court->id);
                })
                ->get();
        if ($rules->isEmpty()) {
            return [];
        }

        $midnight = Carbon::parse($localDate->toDateString() . ' 00:00:00', $tz);
        $segments = [];
        for ($m = $fromMin; $m < $toMin; $m += 5) {
            $rule = $this->bestRuleFor($rules, $court, $midnight->copy()->addMinutes($m));
            $rate = $rule ? (float) $rule->price_per_hour : null;
            $last = count($segments) - 1;
            if ($last >= 0 && $segments[$last][2] === $rate && $segments[$last][1] === $m) {
                $segments[$last][1] = $m + 5;
            } else {
                $segments[] = [$m, $m + 5, $rate];
            }
        }

        return $segments;
    }

    /**
     * Таблица стоимости аренды направления по дням недели для публичной страницы площадки:
     * корты с одинаковой сеткой цен объединяются, дни с одинаковыми интервалами — в диапазоны («Пн–Пт»).
     *
     * @return array<int, array{courts: string[], rows: array<int, array{days: int[], segments: array<int, array{0:int,1:int,2:float}>|null}>}>
     */
    public function weeklyPriceTable(\App\Models\LocationDirection $direction): array
    {
        $direction->loadMissing(['location.city', 'workingHours', 'courts']);
        $courts = $direction->courts->where('is_active', true)->values();
        if ($courts->isEmpty()) {
            return [];
        }

        $rules = CourtPriceRule::where('direction_id', $direction->id)->get();
        if ($rules->isEmpty()) {
            return [];
        }

        $tz = $direction->location?->effectiveTimezone() ?: 'Europe/Moscow';
        $monday = Carbon::now($tz)->startOfWeek();
        $hours = $direction->workingHours->keyBy('day_of_week');

        $groups = [];
        foreach ($courts as $court) {
            $perDay = [];
            for ($dow = 0; $dow < 7; $dow++) {
                $wh = $hours->get($dow);
                if (!$wh || $wh->is_day_off) {
                    $perDay[$dow] = null;
                    continue;
                }
                $segs = $this->ratesForDay($court, $monday->copy()->addDays($dow), $this->timeToMin($wh->opens_at), $this->timeToMin($wh->closes_at), $rules);
                $perDay[$dow] = array_values(array_filter($segs, fn ($x) => $x[2] !== null));
            }
            $sig = md5(json_encode($perDay));
            $groups[$sig]['courts'][] = $court->name;
            $groups[$sig]['perDay'] = $perDay;
        }

        $out = [];
        foreach ($groups as $g) {
            $rows = [];
            foreach ($g['perDay'] as $dow => $segs) {
                $last = count($rows) - 1;
                if ($last >= 0 && $rows[$last]['segments'] === $segs && end($rows[$last]['days']) === $dow - 1) {
                    $rows[$last]['days'][] = $dow;
                } else {
                    $rows[] = ['days' => [$dow], 'segments' => $segs];
                }
            }
            $out[] = ['courts' => $g['courts'], 'rows' => $rows];
        }

        return $out;
    }

    private function bestRuleFor(Collection $rules, LocationCourt $court, Carbon $moment): ?CourtPriceRule
    {
        $dayOfWeek = $moment->dayOfWeekIso - 1; // 0=Пн ... 6=Вс
        $minuteOfDay = $moment->hour * 60 + $moment->minute;

        $matching = $rules->filter(function (CourtPriceRule $rule) use ($court, $dayOfWeek, $minuteOfDay) {
            if ($rule->court_id !== null && (int) $rule->court_id !== (int) $court->id) {
                return false;
            }
            if ($rule->day_of_week !== null && (int) $rule->day_of_week !== $dayOfWeek) {
                return false;
            }
            if ($rule->starts_at !== null && $rule->ends_at !== null) {
                $ruleStart = $this->timeToMin($rule->starts_at);
                $ruleEnd = $this->timeToMin($rule->ends_at);
                if ($minuteOfDay < $ruleStart || $minuteOfDay >= $ruleEnd) {
                    return false;
                }
            }
            return true;
        });

        if ($matching->isEmpty()) {
            return null;
        }

        return $matching->sort(function (CourtPriceRule $a, CourtPriceRule $b) {
            if ($a->priority !== $b->priority) {
                return $b->priority <=> $a->priority;
            }
            $aCourtSpecific = $a->court_id !== null ? 1 : 0;
            $bCourtSpecific = $b->court_id !== null ? 1 : 0;
            if ($aCourtSpecific !== $bCourtSpecific) {
                return $bCourtSpecific <=> $aCourtSpecific;
            }
            $aDaySpecific = $a->day_of_week !== null ? 1 : 0;
            $bDaySpecific = $b->day_of_week !== null ? 1 : 0;
            if ($aDaySpecific !== $bDaySpecific) {
                return $bDaySpecific <=> $aDaySpecific;
            }
            $aTimeSpecific = $a->starts_at !== null ? 1 : 0;
            $bTimeSpecific = $b->starts_at !== null ? 1 : 0;
            return $bTimeSpecific <=> $aTimeSpecific;
        })->first();
    }

    private function timeToMin(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', substr($time, 0, 5)));
        return $h * 60 + $m;
    }
}
