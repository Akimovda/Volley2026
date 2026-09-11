<?php

namespace App\Services;

use App\Models\TrainerRating;

class TrainerRatingService
{
    /**
     * Средний балл + количество оценок по всем турам тренера (без фильтра по occurrence).
     */
    public function summary(int $trainerUserId): array
    {
        $ratings = TrainerRating::where('trainer_user_id', $trainerUserId)->get();

        $count = $ratings->count();
        $avg   = $count ? round($ratings->avg('score'), 1) : null;

        return [
            'avg'          => $avg,
            'count'        => $count,
            'distribution' => $this->distribution($ratings),
        ];
    }

    /**
     * Распределение оценок 1..10 => количество голосов.
     */
    private function distribution(\Illuminate\Support\Collection $ratings): array
    {
        $distribution = array_fill(1, 10, 0);
        foreach ($ratings as $rating) {
            $distribution[(int) $rating->score] = ($distribution[(int) $rating->score] ?? 0) + 1;
        }
        return $distribution;
    }
}
