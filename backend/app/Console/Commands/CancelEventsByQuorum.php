<?php

namespace App\Console\Commands;

use App\Services\UserNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CancelEventsByQuorum extends Command
{
    protected $signature = 'events:cancel-by-quorum
        {--dry-run : Only show what would be cancelled}
        {--limit=200 : Max occurrences to process}';

    protected $description = 'Cancel occurrences automatically if min_players is not reached by the quorum deadline.';

    public function handle(): int
    {
        if (!Schema::hasTable('event_occurrences')) {
            $this->warn('event_occurrences table not found — skip');
            return self::SUCCESS;
        }

        if (!Schema::hasTable('events')) {
            $this->warn('events table not found — skip');
            return self::SUCCESS;
        }

        if (!Schema::hasTable('event_game_settings')) {
            $this->warn('event_game_settings table not found — skip');
            return self::SUCCESS;
        }

        if (!Schema::hasTable('event_registrations')) {
            $this->warn('event_registrations table not found — skip');
            return self::SUCCESS;
        }

        $limit = max(1, (int) ($this->option('limit') ?? 200));
        $dryRun = (bool) $this->option('dry-run');
        $nowUtc = Carbon::now('UTC');

        $q = DB::table('event_occurrences as eo')
            ->join('events as e', 'e.id', '=', 'eo.event_id')
            ->join('event_game_settings as egs', 'egs.event_id', '=', 'e.id')
            ->leftJoin('locations as loc', 'loc.id', '=', 'eo.location_id')
            ->select([
                'eo.id',
                'eo.event_id',
                'eo.starts_at',
                'eo.timezone',
                'eo.registration_ends_at',
                'e.title as event_title',
                'egs.min_players',
                'loc.name as location_name',
                'loc.address as location_address',
            ])
            ->whereNotNull('eo.starts_at')
            ->whereNotNull('eo.registration_ends_at')
            ->whereNotNull('egs.min_players')
            ->where('egs.min_players', '>', 0);

        if (Schema::hasColumn('event_occurrences', 'cancelled_at')) {
            $q->whereNull('eo.cancelled_at');
        }

        if (Schema::hasColumn('event_occurrences', 'is_cancelled')) {
            $q->where(function ($w) {
                $w->whereNull('eo.is_cancelled')
                  ->orWhere('eo.is_cancelled', false);
            });
        }

        $rows = $q->orderBy('eo.starts_at', 'asc')
            ->limit($limit)
            ->get();

        $checked = 0;
        $cancelled = 0;
        $skipped = 0;

        /** @var UserNotificationService $notifications */
        $notifications = app(UserNotificationService::class);

        foreach ($rows as $row) {
            $checked++;

            $startsUtc = Carbon::parse($row->starts_at, 'UTC');
            $timezone = (string) ($row->timezone ?: 'UTC');
            $minPlayers = (int) $row->min_players;

            // Триггер — момент окончания регистрации
            $regEndsUtc = Carbon::parse($row->registration_ends_at, 'UTC');

            // Ещё не наступило время окончания регистрации
            if ($nowUtc->lt($regEndsUtc)) {
                $skipped++;
                continue;
            }

            // Мероприятие уже началось — не трогаем
            if ($nowUtc->gte($startsUtc)) {
                $skipped++;
                continue;
            }

            $activeRegs = $this->countActiveRegistrations((int) $row->event_id, (int) $row->id);

            if ($activeRegs >= $minPlayers) {
                $skipped++;
                continue;
            }

            $userIds = $this->getActiveRegisteredUserIds((int) $row->event_id, (int) $row->id);

            $startsLocalText = $startsUtc->copy()
                ->setTimezone($timezone)
                ->format('d.m.Y H:i') . ' (' . $timezone . ')';

            if ($dryRun) {
                $this->line(
                    sprintf(
                        '[dry-run] cancel occurrence=%d event=%d regs=%d min=%d start=%s',
                        (int) $row->id,
                        (int) $row->event_id,
                        $activeRegs,
                        $minPlayers,
                        $startsLocalText
                    )
                );
                $cancelled++;
                continue;
            }

            DB::transaction(function () use ($row, $userIds, $notifications, $startsLocalText) {
                $payload = [
                    'updated_at' => now(),
                ];

                if (Schema::hasColumn('event_occurrences', 'cancelled_at')) {
                    $payload['cancelled_at'] = now();
                }

                if (Schema::hasColumn('event_occurrences', 'is_cancelled')) {
                    $payload['is_cancelled'] = true;
                }

                DB::table('event_occurrences')
                    ->where('id', (int) $row->id)
                    ->update($payload);

                $locationText = implode(', ', array_filter([
                    $row->location_name ?? null,
                    $row->location_address ?? null,
                ]));


                foreach ($userIds as $userId) {
                    $user = \App\Models\User::find((int) $userId);
                    $userName = null;
                    if ($user) {
                        $full = trim(($user->last_name ?? '') . ' ' . ($user->first_name ?? ''));
                        $userName = $full !== '' ? $full : ($user->name ?? null);
                    }

                    $notifications->createEventCancelledByQuorumNotification(
                        userId: (int) $userId,
                        eventId: (int) $row->event_id,
                        occurrenceId: (int) $row->id,
                        eventTitle: (string) ($row->event_title ?? ('Мероприятие #' . $row->event_id)),
                        startsAtText: $startsLocalText,
                        locationText: $locationText ?: null,
                        userName: $userName,
                    );
                }
            });

            $this->info(
                sprintf(
                    'Cancelled occurrence=%d event=%d regs=%d min=%d start=%s',
                    (int) $row->id,
                    (int) $row->event_id,
                    $activeRegs,
                    $minPlayers,
                    $startsLocalText
                )
            );

            $cancelled++;
        }

        $this->info("Done. checked={$checked}, cancelled={$cancelled}, skipped={$skipped}, dryRun=" . ($dryRun ? 'yes' : 'no'));

        return self::SUCCESS;
    }

    private function resolveQuorumCancelAt(Carbon $startsAtUtc, string $timezone): Carbon
    {
        $local = $startsAtUtc->copy()->setTimezone($timezone);
        $hour = (int) $local->format('H');

        // 07:00 - 09:59 => за 8 часов
        if ($hour >= 7 && $hour <= 9) {
            return $startsAtUtc->copy()->subHours(8);
        }

        // 10:00 - 23:59 => за 40 минут
        if ($hour >= 10 && $hour <= 23) {
            return $startsAtUtc->copy()->subMinutes(40);
        }

        // 00:00 - 06:59 => тоже за 8 часов
        return $startsAtUtc->copy()->subHours(8);
    }

    private function countActiveRegistrations(int $eventId, int $occurrenceId): int
    {
        $q = DB::table('event_registrations')
            ->where('event_id', $eventId);

        if (Schema::hasColumn('event_registrations', 'occurrence_id')) {
            $q->where('occurrence_id', $occurrenceId);
        }

        if (Schema::hasColumn('event_registrations', 'cancelled_at')) {
            $q->whereNull('cancelled_at');
        }

        if (Schema::hasColumn('event_registrations', 'is_cancelled')) {
            $q->where(function ($w) {
                $w->whereNull('is_cancelled')
                  ->orWhere('is_cancelled', false);
            });
        }

        if (Schema::hasColumn('event_registrations', 'status')) {
            $q->where(function ($w) {
                $w->whereNull('status')
                  ->orWhere('status', 'confirmed');
            });
        }

        return (int) $q->count();
    }

    private function getActiveRegisteredUserIds(int $eventId, int $occurrenceId): array
    {
        $q = DB::table('event_registrations')
            ->where('event_id', $eventId);

        if (Schema::hasColumn('event_registrations', 'occurrence_id')) {
            $q->where('occurrence_id', $occurrenceId);
        }

        if (Schema::hasColumn('event_registrations', 'cancelled_at')) {
            $q->whereNull('cancelled_at');
        }

        if (Schema::hasColumn('event_registrations', 'is_cancelled')) {
            $q->where(function ($w) {
                $w->whereNull('is_cancelled')
                  ->orWhere('is_cancelled', false);
            });
        }

        if (Schema::hasColumn('event_registrations', 'status')) {
            $q->where(function ($w) {
                $w->whereNull('status')
                  ->orWhere('status', 'confirmed');
            });
        }

        return $q->pluck('user_id')
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values()
            ->all();
    }
}
