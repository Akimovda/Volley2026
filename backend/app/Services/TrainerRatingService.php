<?php

namespace App\Services;

use App\Models\EventOccurrence;
use App\Models\EventRegistration;
use App\Models\TrainerRating;
use Carbon\Carbon;

class TrainerRatingService
{
    public const EDIT_WINDOW_DAYS = 14;

    public function __construct(private TrainerResolverService $resolver)
    {
    }

    /**
     * Создать/обновить оценку тренера игроком.
     *
     * Eligibility (иначе 403):
     *  - у $raterUserId есть активная регистрация на $occurrenceId
     *  - тур уже завершился (occurrence->isFinished())
     *  - $trainerUserId входит в эффективный список тренеров тура (§2.1: occurrence->trainers,
     *    иначе event->trainers)
     *
     * Правка существующей оценки — только в течение self::EDIT_WINDOW_DAYS дней от starts_at
     * тура, иначе 422.
     */
    public function rate(int $occurrenceId, int $trainerUserId, int $raterUserId, int $score, ?string $comment = null): TrainerRating
    {
        if ($score < 1 || $score > 10) {
            abort(422, 'Оценка должна быть от 1 до 10.');
        }

        $occurrence = EventOccurrence::with('event')->findOrFail($occurrenceId);

        $hasRegistration = EventRegistration::where('occurrence_id', $occurrenceId)
            ->where('user_id', $raterUserId)
            ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
            ->exists();
        abort_unless($hasRegistration, 403, 'Оценивать тренера может только участник этого тура.');

        abort_unless($occurrence->isFinished(), 403, 'Оценить тренера можно только после завершения тура.');

        $effectiveTrainerIds = $this->resolver->effectiveTrainerIds($occurrence);
        abort_unless(in_array($trainerUserId, $effectiveTrainerIds, true), 403, 'Этот пользователь не был тренером на данном туре.');

        $existing = TrainerRating::where('occurrence_id', $occurrenceId)
            ->where('trainer_user_id', $trainerUserId)
            ->where('rater_user_id', $raterUserId)
            ->first();

        if ($existing) {
            $deadline = Carbon::parse($occurrence->starts_at)->addDays(self::EDIT_WINDOW_DAYS);
            abort_if(now()->gt($deadline), 422, 'Окно правки оценки (14 дней с даты тура) истекло.');

            $existing->update(['score' => $score, 'comment' => $comment]);
            return $existing;
        }

        return TrainerRating::create([
            'occurrence_id'   => $occurrenceId,
            'trainer_user_id' => $trainerUserId,
            'rater_user_id'   => $raterUserId,
            'score'           => $score,
            'comment'         => $comment,
        ]);
    }

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
