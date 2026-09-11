<?php

namespace App\Services;

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
}
