<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SendEventRegistrationReminders extends Command
{
    protected $signature = 'events:send-registration-reminders
        {--minutes= : Override remind minutes before start (int)}
        {--dry-run : Do not write delivery records / do not actually send}
        {--limit=300 : Max registrations to process}';

    protected $description = 'Send reminders to users registered to upcoming occurrences/events (registration reminders).';

    public function handle(): int
    {
        // База: должны быть регистрации
        if (!Schema::hasTable('event_registrations')) {
            $this->warn('event_registrations table not found — skip');
            return self::SUCCESS;
        }

        $hasOccTable = Schema::hasTable('event_occurrences');
        $hasEvents   = Schema::hasTable('events');
        if (!$hasOccTable || !$hasEvents) {
            $this->warn('events/event_occurrences not found — skip');
            return self::SUCCESS;
        }

        $limit = (int)($this->option('limit') ?? 300);
        if ($limit < 1) $limit = 1;

        $nowUtc = Carbon::now('UTC');

        $overrideMinutes = $this->option('minutes');
        $overrideMinutes = is_null($overrideMinutes) ? null : (int)$overrideMinutes;

        $dryRun = (bool)$this->option('dry-run');

        // delivery tracking (чтобы не слать повторно)
        $hasDeliveries = Schema::hasTable('notification_deliveries');

        // Колонки, которые могут/не могут быть в events / occurrences
        $hasOccStarts = Schema::hasColumn('event_occurrences', 'starts_at');

        $hasOccRemEnabled = Schema::hasColumn('event_occurrences', 'remind_registration_enabled');
        $hasOccRemMinutes = Schema::hasColumn('event_occurrences', 'remind_registration_minutes_before');

        $hasEvRemEnabled  = Schema::hasColumn('events', 'remind_registration_enabled');
        $hasEvRemMinutes  = Schema::hasColumn('events', 'remind_registration_minutes_before');

        if (!$hasOccStarts) {
            $this->warn('event_occurrences.starts_at not found — skip');
            return self::SUCCESS;
        }

        // Строим кандидатов: регистрации на occurrence, где старт “скоро”
        // minutesBefore берём: override -> occurrence -> event -> default 60
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

        // только “активные” регистрации (мягко, если колонок нет)
        if (Schema::hasColumn('event_registrations', 'deleted_at')) {
            $q->whereNull('er.deleted_at');
        }
        if (Schema::hasColumn('event_registrations', 'cancelled_at')) {
            $q->whereNull('er.cancelled_at');
        }
        if (Schema::hasColumn('event_registrations', 'is_cancelled')) {
            $q->where('er.is_cancelled', false);
        }
        if (Schema::hasColumn('event_registrations', 'status')) {
            $q->where('er.status', 'confirmed');
        }

        // Напоминания включены (occurrence overrides event)
        $q->where(function ($w) use ($hasOccRemEnabled, $hasEvRemEnabled) {
            if ($hasOccRemEnabled) {
                $w->where(function ($x) {
                    $x->where('eo.remind_registration_enabled', true);
                })->orWhere(function ($x) use ($hasEvRemEnabled) {
                    // если у occurrence нет true — смотрим event
                    if ($hasEvRemEnabled) {
                        $x->whereNull('eo.remind_registration_enabled')
                          ->where('e.remind_registration_enabled', true);
                    } else {
                        $x->whereRaw('1=0');
                    }
                });
            } else {
                // если в occurrence нет колонки — только по event
                if ($hasEvRemEnabled) {
                    $w->where('e.remind_registration_enabled', true);
                } else {
                    $w->whereRaw('1=0');
                }
            }
        });

        // Фильтр по времени: starts_at в ближайшие (minutesBefore) минут,
        // но minutesBefore у всех может быть разный — поэтому делаем “широкое окно”,
        // а точное окно проверим уже в PHP.
        $maxWindow = $overrideMinutes ?? 10080; // до недели
        $q->whereBetween('eo.starts_at', [
            $nowUtc->copy()->subMinutes(1),               // чуть назад (на случай лагов)
            $nowUtc->copy()->addMinutes($maxWindow + 1),  // вперёд
        ]);

        // исключаем тех, кому уже отправили (если есть tracking)
        if ($hasDeliveries) {
            $q->leftJoin('notification_deliveries as nd', function ($j) {
                $j->on('nd.user_id', '=', 'er.user_id')
                  ->on('nd.occurrence_id', '=', 'er.occurrence_id')
                  ->where('nd.type', '=', 'registration_reminder');
            })
            ->whereNull('nd.id');
        }

        $rows = $q->orderBy('eo.starts_at', 'asc')
            ->limit($limit)
            ->get();

        $sent = 0;
        $skipped = 0;

        foreach ($rows as $r) {
            $startsUtc = Carbon::parse($r->starts_at, 'UTC');

            $minutesBefore = 60;
            if (!is_null($overrideMinutes)) {
                $minutesBefore = $overrideMinutes;
            } else {
                // occurrence minutes override event minutes
                $occMinutes = null;
                $evMinutes  = null;

                if ($hasOccRemMinutes) {
                    $occMinutes = DB::table('event_occurrences')->where('id', (int)$r->occurrence_id)->value('remind_registration_minutes_before');
                }
                if (is_null($occMinutes) && $hasEvRemMinutes) {
                    $evMinutes = DB::table('events')->where('id', (int)$r->event_id)->value('remind_registration_minutes_before');
                }

                $raw = is_null($occMinutes) ? $evMinutes : $occMinutes;
                $raw = is_null($raw) ? 60 : (int)$raw;
                $minutesBefore = max(0, min(10080, $raw));
            }

            // точное условие “пора ли”
            $fireAt = $startsUtc->copy()->subMinutes($minutesBefore);
            if ($nowUtc->lt($fireAt)) {
                $skipped++;
                continue;
            }

            // если уже началось — смысла нет
            if ($nowUtc->gte($startsUtc)) {
                $skipped++;
                continue;
            }

            $tz = (string)($r->timezone ?: 'UTC');
            $startsLocal = $startsUtc->copy()->setTimezone($tz)->format('d.m.Y H:i');

            // Здесь можно заменить на Notification / Telegram / email — что у тебя есть.
            // Сейчас сделаем безопасно: лог + запись delivery, чтобы не спамить.
            $msg = sprintf(
                'Reminder: %s (occurrence #%d) starts at %s (%s)',
                (string)($r->event_title ?? 'Event'),
                (int)$r->occurrence_id,
                $startsLocal,
                $tz
            );

            if (!$dryRun) {
                Log::info($msg, [
                    'type' => 'registration_reminder',
                    'user_id' => (int)$r->user_id,
                    'event_id' => (int)$r->event_id,
                    'occurrence_id' => (int)$r->occurrence_id,
                    'starts_at_utc' => $startsUtc->toIso8601String(),
                ]);

                if ($hasDeliveries) {
                    DB::table('notification_deliveries')->insert([
                        'type' => 'registration_reminder',
                        'user_id' => (int)$r->user_id,
                        'event_id' => (int)$r->event_id,
                        'occurrence_id' => (int)$r->occurrence_id,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]);
                }
            }

            $sent++;
        }

        $this->info("Done. sent={$sent}, skipped={$skipped}, dryRun=" . ($dryRun ? 'yes' : 'no'));
        return self::SUCCESS;
    }
}
