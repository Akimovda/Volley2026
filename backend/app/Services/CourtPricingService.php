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
        $tz = $location?->timezone ?: 'Europe/Moscow';

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
