<?php

namespace App\Services;

use App\Models\EventOccurrence;
use Illuminate\Support\Facades\DB;

/**
 * Единая живая проверка общей вместимости основного состава тура для автозаписи
 * (абонемент / Premium). Вызывать под pg_advisory_xact_lock(occurrence_id, roleKey).
 *
 * - лимит — effectiveMaxPlayers(): переопределение тура, иначе game_settings (раньше
 *   автозапись по абонементу читала только game_settings и игнорировала override тура);
 * - «занято» — активные регистрации по ВСЕМ трём признакам отмены (cancelled_at,
 *   is_cancelled, status='cancelled'; раньше считался только is_cancelled), без резерва;
 * - max_players <= 0 — лимита нет.
 */
class OccurrenceCapacityService
{
    public function mainRegisteredCount(int $occurrenceId): int
    {
        return (int) DB::table('event_registrations')
            ->where('occurrence_id', $occurrenceId)
            ->whereNull('cancelled_at')
            ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
            ->whereRaw("(status IS NULL OR status <> 'cancelled')")
            ->whereRaw("(position IS NULL OR position <> 'reserve')")
            ->count();
    }

    public function hasRoom(EventOccurrence $occurrence): bool
    {
        $occurrence->loadMissing('event.gameSettings');
        $max = $occurrence->effectiveMaxPlayers();

        return $max <= 0 || $this->mainRegisteredCount((int) $occurrence->id) < $max;
    }
}
