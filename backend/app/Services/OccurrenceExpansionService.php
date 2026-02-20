<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventOccurrence;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RRule\RRule;


final class OccurrenceExpansionService
{
    

        public function expand(Event $event, int $horizonDays = 90, int $maxCreates = 500): int
        {
            $isRecurring = (bool)($event->is_recurring ?? false);
            $recRule = trim((string)($event->recurrence_rule ?? ''));
        
            if (!$isRecurring || $recRule === '' || !$event->starts_at) {
                return 0;
            }
        
            // ✅ Быстрая защита от мусора (на случай если всё-таки просочится)
            if (!preg_match('/\bFREQ=(DAILY|WEEKLY|MONTHLY|YEARLY)\b/i', $recRule)) {
                Log::warning('Skip expand: invalid recurrence_rule', [
                    'event_id' => $event->id,
                    'rule' => $recRule,
                ]);
                return 0;
            }
        
            $tz = (string)($event->timezone ?: 'UTC');
        
            $nowUtc = CarbonImmutable::now('UTC');
            $horizonUtc = $nowUtc->addDays(max(1, $horizonDays))->endOfDay();
        
            // duration
            $durationSec = null;
            if ($event->starts_at && $event->ends_at) {
                $s = CarbonImmutable::parse($event->starts_at, 'UTC');
                $e = CarbonImmutable::parse($event->ends_at, 'UTC');
                $d = $e->diffInSeconds($s);
                $durationSec = ($d > 0) ? $d : null;
            }
        
            // offsets (пока дефолты)
            $daysBefore      = 3;
            $endsMinBefore   = 15;
            $cancelMinBefore = 60;
        
            // max_players snapshot
            $maxPlayersSnapshot = null;
            $gs = $event->relationLoaded('gameSettings') ? $event->gameSettings : $event->gameSettings()->first();
            if ($gs && isset($gs->max_players)) {
                $maxPlayersSnapshot = (int)$gs->max_players;
            }
        
            // DTSTART в локальной TZ события
            $dtStartLocal = CarbonImmutable::parse($event->starts_at, 'UTC')->setTimezone($tz);
        
            // ✅ Важно: TZID + без массивов, только строка
            $ical = "DTSTART;TZID={$tz}:" . $dtStartLocal->format('Ymd\THis') . "\nRRULE:" . $recRule;
        
            try {
                $rr = new RRule($ical);
            } catch (\Throwable $e) {
                Log::warning('Skip expand: RRULE parse failed', [
                    'event_id' => $event->id,
                    'rule' => $recRule,
                    'err' => $e->getMessage(),
                ]);
                return 0;
            }
        
            $created = 0;
        
            foreach ($rr as $dtLocal) {
                if ($created >= $maxCreates) break;
        
                if (!($dtLocal instanceof \DateTimeInterface)) {
                    continue;
                }
        
                // dtLocal библиотека отдаёт в timezone DTSTART (с TZID)
                $occStartUtc = CarbonImmutable::instance($dtLocal)->setTimezone('UTC');
        
                if ($occStartUtc->gt($horizonUtc)) break;
                if ($occStartUtc->lt($nowUtc)) continue;
        
                $didCreate = $this->createOrUpdateFutureOccurrence(
                    $event,
                    $occStartUtc,
                    $durationSec,
                    $daysBefore,
                    $endsMinBefore,
                    $cancelMinBefore,
                    $maxPlayersSnapshot
                );
        
                if ($didCreate) $created++;
            }
        
            return $created;
        }
    private function createOrUpdateFutureOccurrence(
        Event $event,
        CarbonImmutable $occStartUtc,
        ?int $durationSec,
        int $daysBefore,
        int $endsMinBefore,
        int $cancelMinBefore,
        ?int $maxPlayersSnapshot
    ): bool {
        $occEndUtc = ($durationSec && $durationSec > 0) ? $occStartUtc->addSeconds($durationSec) : null;
        $uniq = "event:{$event->id}:" . $occStartUtc->format('YmdHis');

        $allowReg = (bool)($event->allow_registration ?? false);

        $regStarts = null;
        $regEnds   = null;
        $cancelTil = null;

        if ($allowReg) {
            $regStarts = $occStartUtc->subDays(max(0, $daysBefore));
            $regEnds   = $occStartUtc->subMinutes(max(0, $endsMinBefore));
            $cancelTil = $occStartUtc->subMinutes(max(0, $cancelMinBefore));

            if ($regEnds->greaterThanOrEqualTo($occStartUtc)) $regEnds = $occStartUtc->subMinutes(15);
            if ($regStarts->greaterThan($regEnds)) $regStarts = $regEnds;
            if ($cancelTil->greaterThanOrEqualTo($occStartUtc)) $cancelTil = $occStartUtc->subMinutes(60);
        }

        $nowUtc = CarbonImmutable::now('UTC');

        $existing = EventOccurrence::query()
            ->where('event_id', (int)$event->id)
            ->where('uniq_key', $uniq)
            ->first();

        if ($existing) {
            if ((bool)($existing->is_cancelled ?? false)) return false;

            $raw = $existing->getRawOriginal('starts_at');
            if ($raw) {
                $existingStartUtc = CarbonImmutable::parse($raw, 'UTC');
                if ($existingStartUtc->lt($nowUtc)) return false;
            }

            $existing->fill([
                'ends_at'   => $occEndUtc,
                'timezone'  => $event->timezone ?: 'UTC',
                'location_id'        => (int)($event->location_id ?? 0),
                'allow_registration' => (bool)($event->allow_registration ?? false),
                'classic_level_min'  => $event->classic_level_min,
                'classic_level_max'  => $event->classic_level_max,
                'beach_level_min'    => $event->beach_level_min,
                'beach_level_max'    => $event->beach_level_max,
                'registration_starts_at' => $allowReg ? $regStarts : null,
                'registration_ends_at'   => $allowReg ? $regEnds   : null,
                'cancel_self_until'      => $allowReg ? $cancelTil : null,
                'max_players' => $maxPlayersSnapshot,
            ]);
            $existing->save();

            return false;
        }

        EventOccurrence::create([
            'event_id'  => (int)$event->id,
            'starts_at' => $occStartUtc,
            'ends_at'   => $occEndUtc,
            'timezone'  => $event->timezone ?: 'UTC',
            'uniq_key'  => $uniq,
            'location_id'        => (int)($event->location_id ?? 0),
            'allow_registration' => (bool)($event->allow_registration ?? false),
            'max_players'        => $maxPlayersSnapshot,
            'classic_level_min'  => $event->classic_level_min,
            'classic_level_max'  => $event->classic_level_max,
            'beach_level_min'    => $event->beach_level_min,
            'beach_level_max'    => $event->beach_level_max,
            'registration_starts_at' => $allowReg ? $regStarts : null,
            'registration_ends_at'   => $allowReg ? $regEnds   : null,
            'cancel_self_until'      => $allowReg ? $cancelTil : null,
            'age_policy' => Schema::hasColumn('event_occurrences','age_policy')
                ? (string)($event->age_policy ?? 'any')
                : null,
            'is_snow' => Schema::hasColumn('event_occurrences','is_snow')
                ? (bool)($event->is_snow ?? false)
                : null,
        ]);

        return true;
    }
}
