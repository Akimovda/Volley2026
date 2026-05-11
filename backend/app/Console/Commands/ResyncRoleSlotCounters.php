<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResyncRoleSlotCounters extends Command
{
    protected $signature   = 'event-slots:resync {--event_id= : Resync only this event}';
    protected $description = 'Resync taken_slots counters in event_role_slots to actual registration counts';

    public function handle(): int
    {
        $eventId = $this->option('event_id');

        // Берём ближайший будущий (или последний прошедший) occurrence для каждого события
        $query = DB::table('event_role_slots as ers')
            ->join('events as e', 'e.id', '=', 'ers.event_id')
            ->select('ers.event_id', 'ers.role', 'ers.max_slots', 'ers.taken_slots');

        if ($eventId) {
            $query->where('ers.event_id', (int) $eventId);
        }

        $slots = $query->get();

        if ($slots->isEmpty()) {
            $this->info('No slots found.');
            return 0;
        }

        $updated = 0;

        foreach ($slots as $slot) {
            // Ближайший релевантный occurrence (следующий или последний)
            $occ = DB::table('event_occurrences')
                ->where('event_id', $slot->event_id)
                ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
                ->orderByRaw("ABS(EXTRACT(EPOCH FROM (starts_at - NOW())))")
                ->first(['id']);

            if (!$occ) {
                continue;
            }

            $actual = DB::table('event_registrations')
                ->where('occurrence_id', $occ->id)
                ->where('position', $slot->role)
                ->whereNull('cancelled_at')
                ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
                ->count();

            if ((int) $slot->taken_slots !== $actual) {
                DB::table('event_role_slots')
                    ->where('event_id', $slot->event_id)
                    ->where('role', $slot->role)
                    ->update(['taken_slots' => $actual]);

                $this->line("event={$slot->event_id} role={$slot->role}: {$slot->taken_slots} → {$actual}");
                $updated++;
            }
        }

        $this->info("Done. Updated: $updated slot(s).");
        return 0;
    }
}
