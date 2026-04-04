<?php

namespace App\Console\Commands;

use App\Services\UserNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SendEventRegistrationReminders extends Command
{
    protected $signature = 'events:send-registration-reminders
        {--minutes= : Override remind minutes before start (int)}
        {--dry-run : Do not write notifications / deliveries}
        {--limit=300 : Max registrations to process}';

    protected $description = 'Send reminders to users registered to upcoming occurrences/events (registration reminders).';

    public function handle(): int
    {
        if (!Schema::hasTable('event_registrations')) {
            $this->warn('event_registrations table not found — skip');
            return self::SUCCESS;
        }

        $hasOccTable = Schema::hasTable('event_occurrences');
        $hasEvents = Schema::hasTable('events');

        if (!$hasOccTable || !$hasEvents) {
            $this->warn('events/event_occurrences not found — skip');
            return self::SUCCESS;
        }

        $limit = (int) ($this->option('limit') ?? 300);
        if ($limit < 1) {
            $limit = 1;
        }

        $nowUtc = Carbon::now('UTC');

        $overrideMinutes = $this->option('minutes');
        $overrideMinutes = is_null($overrideMinutes) ? null : (int) $overrideMinutes;

        $dryRun = (bool) $this->option('dry-run');

        $hasDeliveries = Schema::hasTable('notification_deliveries');

        $hasOccStarts = Schema::hasColumn('event_occurrences', 'starts_at');
        $hasOccRemEnabled = Schema::hasColumn('event_occurrences', 'remind_registration_enabled');
        $hasOccRemMinutes = Schema::hasColumn('event_occurrences', 'remind_registration_minutes_before');
        $hasEvRemEnabled = Schema::hasColumn('events', 'remind_registration_enabled');
        $hasEvRemMinutes = Schema::hasColumn('events', 'remind_registration_minutes_before');

        if (!$hasOccStarts) {
            $this->warn('event_occurrences.starts_at not found — skip');
            return self::SUCCESS;
        }

        $q = DB::table('event_registrations as er')
            ->join('event_occurrences as eo', 'eo.id', '=', 'er.occurrence_id')
            ->join('events as e', 'e.id', '=', 'eo.event_id')
            ->join('users as u', 'u.id', '=', 'er.user_id')
            ->select([
                'er.id as registration_id',
                'er.user_id',
                'er.occurrence_id',
                'eo.event_id',
                'eo.starts_at',
                'eo.timezone',
                'e.title as event_title',
            ])
            ->whereNotNull('er.occurrence_id');

        if (Schema::hasColumn('event_registrations', 'deleted_at')) {
            $q->whereNull('er.deleted_at');
        }

        if (Schema::hasColumn('event_registrations', 'cancelled_at')) {
            $q->whereNull('er.cancelled_at');
        }

        if (Schema::hasColumn('event_registrations', 'is_cancelled')) {
            $q->where(function ($w) {
                $w->whereNull('er.is_cancelled')
                  ->orWhere('er.is_cancelled', false);
            });
        }

        if (Schema::hasColumn('event_registrations', 'status')) {
            $q->where(function ($w) {
                $w->whereNull('er.status')
                  ->orWhere('er.status', 'confirmed');
            });
        }

        $q->where(function ($w) use ($hasOccRemEnabled, $hasEvRemEnabled) {
            if ($hasOccRemEnabled) {
                $w->where(function ($x) {
                    $x->where('eo.remind_registration_enabled', true);
                })->orWhere(function ($x) use ($hasEvRemEnabled) {
                    if ($hasEvRemEnabled) {
                        $x->whereNull('eo.remind_registration_enabled')
                          ->where('e.remind_registration_enabled', true);
                    } else {
                        $x->whereRaw('1=0');
                    }
                });
            } else {
                if ($hasEvRemEnabled) {
                    $w->where('e.remind_registration_enabled', true);
                } else {
                    $w->whereRaw('1=0');
                }
            }
        });

        $maxWindow = $overrideMinutes ?? 10080;

        $q->whereBetween('eo.starts_at', [
            $nowUtc->copy()->subMinutes(1),
            $nowUtc->copy()->addMinutes($maxWindow + 1),
        ]);

        $rows = $q->orderBy('eo.starts_at', 'asc')
            ->limit($limit)
            ->get();

        $sent = 0;
        $skipped = 0;

        /** @var UserNotificationService $notifications */
        $notifications = app(UserNotificationService::class);

        foreach ($rows as $r) {
            $startsUtc = Carbon::parse($r->starts_at, 'UTC');

            $minutesBefore = 60;

            if (!is_null($overrideMinutes)) {
                $minutesBefore = $overrideMinutes;
            } else {
                $occMinutes = null;
                $evMinutes = null;

                if ($hasOccRemMinutes) {
                    $occMinutes = DB::table('event_occurrences')
                        ->where('id', (int) $r->occurrence_id)
                        ->value('remind_registration_minutes_before');
                }

                if (is_null($occMinutes) && $hasEvRemMinutes) {
                    $evMinutes = DB::table('events')
                        ->where('id', (int) $r->event_id)
                        ->value('remind_registration_minutes_before');
                }

                $raw = is_null($occMinutes) ? $evMinutes : $occMinutes;
                $raw = is_null($raw) ? 60 : (int) $raw;
                $minutesBefore = max(0, min(10080, $raw));
            }

            $fireAt = $startsUtc->copy()->subMinutes($minutesBefore);

            if ($nowUtc->lt($fireAt)) {
                $skipped++;
                continue;
            }

            if ($nowUtc->gte($startsUtc)) {
                $skipped++;
                continue;
            }

            if ($hasDeliveries) {
                $alreadySent = DB::table('notification_deliveries')
                    ->where('type', 'event_reminder')
                    ->where('user_id', (int) $r->user_id)
                    ->where('event_id', (int) $r->event_id)
                    ->where('occurrence_id', (int) $r->occurrence_id)
                    ->exists();

                if ($alreadySent) {
                    $skipped++;
                    continue;
                }
            }

            $tz = (string) ($r->timezone ?: 'UTC');
            $startsLocalText = $startsUtc->copy()->setTimezone($tz)->format('d.m.Y H:i') . ' (' . $tz . ')';

            if ($dryRun) {
                $this->line(sprintf(
                    '[dry-run] reminder user=%d event=%d occurrence=%d starts=%s',
                    (int) $r->user_id,
                    (int) $r->event_id,
                    (int) $r->occurrence_id,
                    $startsLocalText
                ));
                $sent++;
                continue;
            }

            $notifications->createEventReminderNotification(
                userId: (int) $r->user_id,
                eventId: (int) $r->event_id,
                occurrenceId: (int) $r->occurrence_id,
                eventTitle: (string) ($r->event_title ?? ('Мероприятие #' . $r->event_id)),
                startsAtText: $startsLocalText
            );

            Log::info('Registration reminder notification created', [
                'type' => 'event_reminder',
                'user_id' => (int) $r->user_id,
                'event_id' => (int) $r->event_id,
                'occurrence_id' => (int) $r->occurrence_id,
                'starts_at_utc' => $startsUtc->toIso8601String(),
                'starts_at_local' => $startsLocalText,
            ]);

            $sent++;
        }

        $this->info("Done. sent={$sent}, skipped={$skipped}, dryRun=" . ($dryRun ? 'yes' : 'no'));

        return self::SUCCESS;
    }
}