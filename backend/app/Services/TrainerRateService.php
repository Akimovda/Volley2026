<?php

namespace App\Services;

use App\Models\EventTrainerRate;
use App\Models\SchoolTrainerRate;
use App\Models\VolleyballSchool;
use Carbon\CarbonInterface;
use DomainException;

class TrainerRateService
{
    /**
     * Ставка — версионируемая история: каждый вызов создаёт НОВУЮ строку,
     * ничего не перезаписывает (см. resolveRate()).
     */
    public function setRate(
        VolleyballSchool $school,
        int $userId,
        string $rateType,
        float $rate,
        ?CarbonInterface $effectiveFrom,
        int $createdByUserId
    ): SchoolTrainerRate {
        $effectiveFrom = $effectiveFrom ?? $this->defaultEffectiveFrom($school);

        if ($effectiveFrom->lt(now())) {
            throw new DomainException('Дата вступления ставки в силу не может быть в прошлом.');
        }

        return SchoolTrainerRate::create([
            'school_id'          => $school->id,
            'user_id'            => $userId,
            'rate_type'          => $rateType,
            'rate'               => $rate,
            'effective_from'     => $effectiveFrom,
            'created_by_user_id' => $createdByUserId,
        ]);
    }

    /**
     * Завтра 00:00 в таймзоне школы (§2.2) — дефолт, когда организатор не указал дату явно.
     */
    public function defaultEffectiveFrom(VolleyballSchool $school): CarbonInterface
    {
        return now($school->effectiveTimezone())->addDay()->startOfDay();
    }

    /**
     * Резолвер §2.2: последняя по effective_from строка, чей effective_from <= $atStart.
     * Без event override (это фаза 2 — трейт для будущей фазы с привязкой к конкретному occurrence).
     */
    public function effectiveRate(VolleyballSchool $school, int $userId, CarbonInterface $atStart): ?SchoolTrainerRate
    {
        return SchoolTrainerRate::where('school_id', $school->id)
            ->where('user_id', $userId)
            ->where('effective_from', '<=', $atStart)
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * §1.4: ставка на конкретное событие — версионируемая история, аналог setRate().
     */
    public function setEventRate(
        int $eventId,
        int $userId,
        string $rateType,
        float $rate,
        CarbonInterface $effectiveFrom,
        int $createdByUserId
    ): EventTrainerRate {
        if ($effectiveFrom->lt(now())) {
            throw new DomainException('Дата вступления ставки в силу не может быть в прошлом.');
        }

        return EventTrainerRate::create([
            'event_id'           => $eventId,
            'user_id'            => $userId,
            'rate_type'          => $rateType,
            'rate'               => $rate,
            'effective_from'     => $effectiveFrom,
            'created_by_user_id' => $createdByUserId,
        ]);
    }

    /**
     * §1.4: эффективная ставка тренера на дату $atStart с приоритетом
     * event_trainer_rates (ставка на конкретную серию) НАД school_trainer_rates
     * (общая ставка по школе). Возвращает null, если ставка нигде не задана —
     * вызывающая сторона должна трактовать это как "ставка не задана" (плашка),
     * а не как 0.
     *
     * @return array{rate_type:string, rate:float, source:string}|null
     */
    public function effectiveRateForEvent(VolleyballSchool $school, int $eventId, int $userId, CarbonInterface $atStart): ?array
    {
        $eventRate = EventTrainerRate::where('event_id', $eventId)
            ->where('user_id', $userId)
            ->where('effective_from', '<=', $atStart)
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();

        if ($eventRate) {
            return ['rate_type' => $eventRate->rate_type, 'rate' => (float) $eventRate->rate, 'source' => 'event'];
        }

        $schoolRate = $this->effectiveRate($school, $userId, $atStart);
        if ($schoolRate) {
            return ['rate_type' => $schoolRate->rate_type, 'rate' => (float) $schoolRate->rate, 'source' => 'school'];
        }

        return null;
    }
}
