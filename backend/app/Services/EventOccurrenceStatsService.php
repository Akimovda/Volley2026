<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Events\OccurrenceStatsUpdated;
use Illuminate\Support\Facades\Cache;

class EventOccurrenceStatsService
{
    /**
     * Получить количество зарегистрированных
     */
    public function getRegisteredCount(int $occurrenceId): int
    {
        return (int) (
            DB::table('event_occurrence_stats')
                ->where('occurrence_id', $occurrenceId)
                ->value('registered_count') ?? 0
        );
    }

    /**
     * Увеличить счётчик регистраций
     */
    public function increment(int $occurrenceId): void
    {
        DB::statement(
            '
            INSERT INTO event_occurrence_stats (occurrence_id, registered_count, created_at, updated_at)
            VALUES (?, 1, NOW(), NOW())
            ON CONFLICT (occurrence_id)
            DO UPDATE SET
                registered_count = event_occurrence_stats.registered_count + 1,
                updated_at = NOW()
            ',
            [$occurrenceId]
        );
        Cache::forget("event_page:{$occurrenceId}");
        $count = $this->getRegisteredCount($occurrenceId);

        event(new OccurrenceStatsUpdated(
            $occurrenceId,
            $count
        ));
    }
    

    /**
     * Уменьшить счётчик регистраций
     */
    public function decrement(int $occurrenceId): void
    {
        DB::statement(
            '
            INSERT INTO event_occurrence_stats (occurrence_id, registered_count, created_at, updated_at)
            VALUES (?, 0, NOW(), NOW())
            ON CONFLICT (occurrence_id)
            DO UPDATE SET
                registered_count = GREATEST(event_occurrence_stats.registered_count - 1, 0),
                updated_at = NOW()
            ',
            [$occurrenceId]
        );
        Cache::forget("event_page:{$occurrenceId}");
        $count = $this->getRegisteredCount($occurrenceId);

        event(new OccurrenceStatsUpdated(
            $occurrenceId,
            $count
        ));
    }
    
}