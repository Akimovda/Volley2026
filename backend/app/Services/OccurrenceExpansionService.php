<?php
// app/Services/OccurrenceExpansionService.php
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

        // ✅ duration только из event
        $durationSec = (int)($event->duration_sec ?? 0);
        if ($durationSec <= 0) {
            Log::warning('Skip expand: duration_sec missing', [
                'event_id' => $event->id,
            ]);
            return 0;
        }

        // защита от мусорных правил
        if (!preg_match('/\bFREQ=(DAILY|WEEKLY|MONTHLY|YEARLY)\b/i', $recRule)) {
            Log::warning('Skip expand: invalid recurrence_rule', [
                'event_id' => $event->id,
                'rule' => $recRule,
            ]);
            return 0;
        }

        // нормализация UNTIL → UTC
        $recRule = preg_replace(
            '/UNTIL=(\d{8}T\d{6})(?!Z)/',
            'UNTIL=$1Z',
            $recRule
        );

        $tz = (string)($event->timezone ?: 'UTC');

        $nowUtc     = CarbonImmutable::now('UTC');
        $horizonUtc = $nowUtc->addDays(max(1, $horizonDays))->endOfDay();

        // Derive registration offsets from the reference occurrence (first occurrence)
        $regStartsSecBefore = 3 * 86400; // default: 3 days
        $endsMinBefore      = 15;
        $cancelMinBefore    = 60;

        $eventStartUtc = CarbonImmutable::parse($event->starts_at, 'UTC');
        $refUniq = "event:{$event->id}:" . $eventStartUtc->format('YmdHis');
        $refOcc = EventOccurrence::where('event_id', $event->id)
            ->where('uniq_key', $refUniq)
            ->first();

        if ($refOcc && $refOcc->registration_starts_at && $refOcc->registration_ends_at && $refOcc->cancel_self_until) {
            $refStart  = CarbonImmutable::parse($refOcc->registration_starts_at, 'UTC');
            $refEnd    = CarbonImmutable::parse($refOcc->registration_ends_at, 'UTC');
            $refCancel = CarbonImmutable::parse($refOcc->cancel_self_until, 'UTC');
            $regStartsSecBefore = max(0, $eventStartUtc->timestamp - $refStart->timestamp);
            $endsMinBefore      = max(1, (int) round(($eventStartUtc->timestamp - $refEnd->timestamp) / 60));
            $cancelMinBefore    = max(1, (int) round(($eventStartUtc->timestamp - $refCancel->timestamp) / 60));
        }

        // snapshot max_players
        $maxPlayersSnapshot = null;
        $gs = $event->relationLoaded('gameSettings')
            ? $event->gameSettings
            : $event->gameSettings()->first();

        if ($gs && isset($gs->max_players)) {
            $maxPlayersSnapshot = (int)$gs->max_players;
        }

        // DTSTART в TZ события
        $dtStartLocal = CarbonImmutable::parse($event->starts_at, 'UTC')
            ->setTimezone($tz);

        $ical = "DTSTART;TZID={$tz}:" . $dtStartLocal->format('Ymd\THis')
              . "\nRRULE:" . $recRule;

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
            if (!($dtLocal instanceof \DateTimeInterface)) continue;

            $occStartUtc = CarbonImmutable::instance($dtLocal)->setTimezone('UTC');

            if ($occStartUtc->gt($horizonUtc)) break;
            if ($occStartUtc->lt($nowUtc)) continue;

            $didCreate = $this->createOrUpdateFutureOccurrence(
                event: $event,
                occStartUtc: $occStartUtc,
                durationSec: $durationSec,
                regStartsSecBefore: $regStartsSecBefore,
                endsMinBefore: $endsMinBefore,
                cancelMinBefore: $cancelMinBefore,
                maxPlayersSnapshot: $maxPlayersSnapshot
            );

            if ($didCreate) {
                $created++;
            }
        }

        return $created;
    }


    private function createOrUpdateFutureOccurrence(
        Event $event,
        CarbonImmutable $occStartUtc,
        int $durationSec,
        int $regStartsSecBefore,
        int $endsMinBefore,
        int $cancelMinBefore,
        ?int $maxPlayersSnapshot
    ): bool {

        $uniq = "event:{$event->id}:" . $occStartUtc->format('YmdHis');

        $allowReg = (bool)($event->allow_registration ?? false);
        $nowUtc   = CarbonImmutable::now('UTC');

        // ---- registration windows ----
        $regStarts = null;
        $regEnds   = null;
        $cancelTil = null;

        if ($allowReg) {

            $regStarts = $occStartUtc->subSeconds(max(0, $regStartsSecBefore));
            $regEnds   = $occStartUtc->subMinutes(max(0, $endsMinBefore));
            $cancelTil = $occStartUtc->subMinutes(max(0, $cancelMinBefore));

            if ($regEnds->greaterThanOrEqualTo($occStartUtc)) {
                $regEnds = $occStartUtc->subMinutes(15);
            }

            if ($regStarts->greaterThan($regEnds)) {
                $regStarts = $regEnds;
            }

            if ($cancelTil->greaterThanOrEqualTo($occStartUtc)) {
                $cancelTil = $occStartUtc->subMinutes(60);
            }
        }

        $existing = EventOccurrence::query()
            ->where('event_id', (int)$event->id)
            ->where('uniq_key', $uniq)
            ->first();

        if ($existing) {

            if ((bool)($existing->is_cancelled ?? false)) {
                return false;
            }

            $rawStart = $existing->getRawOriginal('starts_at');
            if ($rawStart) {
                $existingStartUtc = CarbonImmutable::parse($rawStart, 'UTC');
                if ($existingStartUtc->lt($nowUtc)) {
                    return false;
                }
            }

            $existing->fill([
                'classic_level_max' => $event->classic_level_max,
                'beach_level_min' => $event->beach_level_min,
                'beach_level_max' => $event->beach_level_max,
                'max_players' => $maxPlayersSnapshot,
                'duration_sec' => $durationSec,
                'registration_starts_at' => $allowReg ? $regStarts : null,
                'registration_ends_at'   => $allowReg ? $regEnds   : null,
                'cancel_self_until'      => $allowReg ? $cancelTil : null,
                'age_policy' => Schema::hasColumn('event_occurrences', 'age_policy')
                    ? (string)($event->age_policy ?? 'any')
                    : null,
                'is_snow' => Schema::hasColumn('event_occurrences', 'is_snow')
                    ? (bool)($event->is_snow ?? false)
                    : null,
            ]);

            $existing->save();
            return false;
        }

        EventOccurrence::create([
            'event_id' => (int)$event->id,
            'starts_at' => $occStartUtc,
            'duration_sec' => $durationSec,
            'timezone' => $event->timezone ?: 'UTC',
            'uniq_key' => $uniq,
            'location_id' => (int)($event->location_id ?? 0),
            'allow_registration' => $allowReg,
            'max_players' => $maxPlayersSnapshot,
            'classic_level_min' => $event->classic_level_min,
            'classic_level_max' => $event->classic_level_max,
            'beach_level_min' => $event->beach_level_min,
            'beach_level_max' => $event->beach_level_max,
            'registration_starts_at' => $allowReg ? $regStarts : null,
            'registration_ends_at'   => $allowReg ? $regEnds   : null,
            'cancel_self_until'      => $allowReg ? $cancelTil : null,
            'age_policy' => Schema::hasColumn('event_occurrences', 'age_policy')
                ? (string)($event->age_policy ?? 'any')
                : null,
            'is_snow' => Schema::hasColumn('event_occurrences', 'is_snow')
                ? (bool)($event->is_snow ?? false)
                : null,
        ]);

        return true;
    }
}