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
        // Командная игра: игроки в составах EventTeam, а не в event_registrations
        $occ = DB::table('event_occurrences as o')
            ->join('events as e', 'e.id', '=', 'o.event_id')
            ->where('o.id', $occurrenceId)
            ->first(['o.id', 'o.event_id', 'e.format', 'e.registration_mode']);

        if ($occ && $occ->format === 'game'
            && in_array((string) $occ->registration_mode, ['team', 'team_classic', 'team_beach'], true)) {
            return $this->teamGamePlayersCount((int) $occ->id, (int) $occ->event_id);
        }

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

    /** Подтверждённые игроки основных (не резервных, не friendly) команд тура. */
    public function teamGamePlayersCount(int $occurrenceId, int $eventId): int
    {
        return \App\Models\EventTeamMember::where('confirmation_status', 'confirmed')
            ->whereHas('team', fn ($q) => $q->where('event_id', $eventId)
                ->where(fn ($q2) => $q2->where('occurrence_id', $occurrenceId)->orWhereNull('occurrence_id'))
                ->whereIn('status', ['ready','pending','pending_members','submitted','confirmed','approved'])
                ->whereNull('reserve_position')
                ->where(fn ($q3) => $q3->whereNull('meta->friendly')->orWhere('meta->friendly', false)))
            ->count();
    }
}
