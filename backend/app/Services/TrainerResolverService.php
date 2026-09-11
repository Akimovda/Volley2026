<?php

namespace App\Services;

use App\Models\EventOccurrence;

/**
 * Единая точка правды для §2.1: эффективный тренер тура.
 * Occurrence override (event_occurrence_trainers), иначе наследование от серии (event_trainers).
 */
class TrainerResolverService
{
    /**
     * @return int[] ID пользователей-тренеров, эффективных на данном occurrence
     */
    public function effectiveTrainerIds(EventOccurrence $occurrence): array
    {
        $occurrence->loadMissing('trainers');
        if ($occurrence->trainers->isNotEmpty()) {
            return $occurrence->trainers->pluck('id')->map(fn ($v) => (int) $v)->all();
        }

        $occurrence->loadMissing('event.trainers');
        return $occurrence->event?->trainers->pluck('id')->map(fn ($v) => (int) $v)->all() ?? [];
    }
}
