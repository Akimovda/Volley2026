<?php

namespace App\Console\Commands;

use App\Models\EventOccurrence;
use App\Models\OccurrenceWaitlist;
use App\Services\WaitlistService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Запускает autoBookNext для мест, у которых только что открылось
 * гендерное окно регистрации (gender_limited_reg_starts_days_before).
 *
 * Без этой команды waitlist-пользователи ограничиваемого пола ждали бы
 * следующей отмены регистрации, чтобы сработал autoBook.
 */
class ProcessWaitlistGenderWindows extends Command
{
    protected $signature   = 'waitlist:process-gender-windows';
    protected $description = 'Trigger autoBook for waitlisted users whose gender registration window just opened';

    public function handle(WaitlistService $waitlist): int
    {
        $now = Carbon::now('UTC');

        // Occurrences where:
        // - event has gender_limited_reg_starts_days_before configured
        // - gender window is NOW open: starts_at - N days <= now
        // - occurrence is upcoming and registration is still open
        // - occurrence is not cancelled
        $rows = DB::table('event_game_settings as gs')
            ->join('events as e', 'e.id', '=', 'gs.event_id')
            ->join('event_occurrences as occ', 'occ.event_id', '=', 'e.id')
            ->select(
                'occ.id as occ_id',
                'gs.gender_limited_side',
                'gs.gender_limited_positions',
                'gs.gender_limited_reg_starts_days_before',
                'occ.starts_at',
                'occ.registration_ends_at'
            )
            ->where('gs.gender_policy', 'mixed_limited')
            ->whereNotNull('gs.gender_limited_reg_starts_days_before')
            ->whereNotNull('occ.registration_ends_at')
            ->where('occ.starts_at', '>', $now)
            ->where('occ.registration_ends_at', '>', $now)
            ->whereRaw('(occ.is_cancelled IS NULL OR occ.is_cancelled = false)')
            // Gender window is open: starts_at - N days ≤ now
            ->whereRaw(
                "(occ.starts_at - (gs.gender_limited_reg_starts_days_before || ' days')::interval) <= ?",
                [$now]
            )
            ->get();

        if ($rows->isEmpty()) {
            return self::SUCCESS;
        }

        $targetGenders = ['male' => 'm', 'female' => 'f'];

        foreach ($rows as $row) {
            $targetGender = $targetGenders[$row->gender_limited_side] ?? null;
            if (!$targetGender) continue;

            // Check: are there waitlisted users of the restricted gender for this occurrence?
            $hasWaitlisted = OccurrenceWaitlist::query()
                ->where('occurrence_id', $row->occ_id)
                ->whereExists(function ($q) use ($targetGender) {
                    $q->from('users')
                      ->whereColumn('users.id', 'occurrence_waitlist.user_id')
                      ->whereRaw('LOWER(SUBSTRING(users.gender, 1, 1)) = ?', [$targetGender]);
                })
                ->exists();

            if (!$hasWaitlisted) continue;

            $positions = $row->gender_limited_positions;
            if (is_string($positions)) {
                $positions = json_decode($positions, true) ?: [];
            }

            Log::info('waitlist:process-gender-windows — triggering autoBook', [
                'occurrence_id'  => $row->occ_id,
                'gender_side'    => $row->gender_limited_side,
                'positions'      => $positions,
            ]);

            $occurrence = EventOccurrence::find($row->occ_id);
            if (!$occurrence) continue;

            foreach ($positions as $position) {
                $waitlist->autoBookNext($occurrence, $position);
            }
        }

        return self::SUCCESS;
    }
}
