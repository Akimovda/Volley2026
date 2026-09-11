<?php

namespace App\Console\Commands;

use App\Models\EventOccurrence;
use App\Models\EventRegistration;
use App\Models\TrainerRating;
use App\Models\User;
use App\Services\TrainerResolverService;
use App\Services\UserNotificationService;
use Illuminate\Console\Command;

/**
 * §6.5: после завершения тура — просьба оценить эффективного тренера каждому
 * confirmed-игроку, у которого ещё нет оценки этого тренера за этот occurrence.
 *
 * Выключено по умолчанию (config('trainers.rating_request_notify_enabled')) —
 * тексты уведомления должны быть согласованы с заказчиком до включения на бою.
 */
class NotifyTrainerRatingRequests extends Command
{
    protected $signature = 'trainers:notify-rating-request {--dry-run}';
    protected $description = 'Уведомить игроков с просьбой оценить тренера после завершения тура';

    public function handle(TrainerResolverService $resolver, UserNotificationService $notifier): int
    {
        if (!config('trainers.rating_request_notify_enabled', false)) {
            $this->info('Отключено конфигом trainers.rating_request_notify_enabled — ничего не делаю.');
            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $maxAgeHours = (int) config('trainers.rating_request_max_age_hours', 6);
        $cutoff = now()->subHours($maxAgeHours);

        $occurrences = EventOccurrence::query()
            ->whereNull('trainer_rating_notified_at')
            ->where(function ($q) {
                $q->whereNull('is_cancelled')->orWhere('is_cancelled', false);
            })
            ->with(['event:id,title', 'trainers:id'])
            ->get()
            ->filter(fn (EventOccurrence $occ) => $occ->isFinished())
            ->filter(function (EventOccurrence $occ) use ($cutoff) {
                $ends = $occ->ends_at_utc;
                return $ends && $ends->gt($cutoff);
            })
            ->values();

        $sent = 0;
        $processedOccurrences = 0;

        foreach ($occurrences as $occ) {
            $trainerIds = $resolver->effectiveTrainerIds($occ);

            if ($trainerIds && $occ->event) {
                $playerIds = EventRegistration::where('occurrence_id', $occ->id)
                    ->where('status', 'confirmed')
                    ->where(function ($q) {
                        $q->whereNull('is_cancelled')->orWhere('is_cancelled', false);
                    })
                    ->pluck('user_id')
                    ->unique()
                    ->values();

                foreach ($trainerIds as $trainerId) {
                    $trainer = User::find($trainerId);
                    if (!$trainer) {
                        continue;
                    }

                    $alreadyRatedBy = TrainerRating::where('occurrence_id', $occ->id)
                        ->where('trainer_user_id', $trainerId)
                        ->whereIn('rater_user_id', $playerIds)
                        ->pluck('rater_user_id')
                        ->all();

                    $eventUrl = route('events.show', ['event' => $occ->event_id]) . '?occurrence=' . $occ->id;

                    foreach ($playerIds as $playerId) {
                        if ((int) $playerId === (int) $trainerId) {
                            continue; // тренер не оценивает сам себя
                        }
                        if (in_array($playerId, $alreadyRatedBy, true)) {
                            continue;
                        }

                        if (!$dryRun) {
                            $notifier->createTrainerRatingRequestNotification(
                                userId: (int) $playerId,
                                eventId: $occ->event_id,
                                occurrenceId: $occ->id,
                                eventTitle: $occ->event->title,
                                trainerName: $trainer->name ?? ('#' . $trainer->id),
                                eventUrl: $eventUrl,
                            );
                        }
                        $sent++;
                    }
                }
            }

            if (!$dryRun) {
                $occ->trainer_rating_notified_at = now();
                $occ->save();
            }
            $processedOccurrences++;
        }

        $prefix = $dryRun ? '[dry-run] ' : '';
        $this->info("{$prefix}Обработано occurrences: {$processedOccurrences}, отправлено уведомлений: {$sent}");

        return self::SUCCESS;
    }
}
