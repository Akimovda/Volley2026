<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class EventOccurrenceStatsService
{
    /**
     * Количество зарегистрированных — живой COUNT из event_registrations.
     * event_occurrence_stats (кеш-таблица) выведена из эксплуатации: не обновлялась
     * симметрично при массовых отменах через QueryBuilder и дрейфовала от реальности
     * (см. report_cache_counters_audit_2026-07-16.md). Write-пути и сама таблица
     * удаляются отдельной миграцией после недели наблюдения.
     */
    public function getRegisteredCount(int $occurrenceId): int
    {
        return (int) DB::table('event_registrations')
            ->where('occurrence_id', $occurrenceId)
            ->whereNull('cancelled_at')
            ->where(function ($q) {
                $q->whereNull('is_cancelled')->orWhere('is_cancelled', false);
            })
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', '!=', 'cancelled');
            })
            ->count();
    }
}
