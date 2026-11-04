<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\RepeatOccurrenceAnnouncementJob;
use App\Models\EventNotificationChannel;
use App\Models\EventOccurrence;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Повтор анонсов в каналы для кемпов, мастер-классов и турниров (настройка «Повторять анонс»: раз в 7 / 14 дней).
 * Повторяем, пока: анонс уже отправлялся и не финализирован, мероприятие не началось и не отменено,
 * регистрация открыта (началась и не закрыта) и есть свободные места. Отсчёт — от последней отправки/повтора.
 * Повтор — НОВОЕ сообщение в канал (прежние остаются), запись переезжает на новое сообщение.
 */
class RepeatChannelAnnouncements extends Command
{
    protected $signature = 'events:repeat-announcements
        {--limit=50 : Max jobs per run}
        {--dry-run : Only show what would be dispatched}';

    protected $description = 'Repeat channel announcements for camps, master classes and tournaments (every 7/14 days while registration is open).';

    public function handle(): int
    {
        if (!Schema::hasColumn('event_notification_channels', 'repeat_interval_days')) {
            return self::SUCCESS;
        }

        $limit  = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');
        $now    = now('UTC');

        $rows = DB::table('event_notification_channels as enc')
            ->join('event_channel_messages as ecm', function ($j) {
                $j->on('ecm.event_id', '=', 'enc.event_id')
                  ->on('ecm.channel_id', '=', 'enc.channel_id')
                  ->where('ecm.notification_type', 'registration_open');
            })
            ->join('event_occurrences as eo', 'eo.id', '=', 'ecm.occurrence_id')
            ->join('events as e', 'e.id', '=', 'enc.event_id')
            ->where('enc.notification_type', 'registration_open')
            ->whereNotNull('enc.repeat_interval_days')
            ->whereIn('e.format', EventNotificationChannel::REPEAT_FORMATS)
            ->whereNotNull('ecm.external_message_id')
            ->whereNull('ecm.announcement_finalized_at')
            ->where('eo.starts_at', '>', $now)
            ->whereNull('eo.cancelled_at')
            ->whereRaw('(eo.is_cancelled IS NULL OR eo.is_cancelled = false)')
            ->select('eo.id as occurrence_id', 'enc.channel_id', 'enc.repeat_interval_days', 'ecm.sent_at', 'ecm.meta')
            ->orderBy('eo.starts_at')
            ->limit(500)
            ->get();

        $dispatched = 0;
        foreach ($rows as $r) {
            if ($dispatched >= $limit) {
                break;
            }

            // отсчёт от последней отправки/повтора
            $meta = json_decode((string) ($r->meta ?? ''), true) ?: [];
            $last = !empty($meta['last_repost_at']) ? Carbon::parse($meta['last_repost_at'], 'UTC') : ($r->sent_at ? Carbon::parse($r->sent_at, 'UTC') : null);
            if (!$last || $last->copy()->addDays((int) $r->repeat_interval_days)->greaterThan($now)) {
                continue;
            }

            $occ = EventOccurrence::query()->with(['event.gameSettings'])->find($r->occurrence_id);
            if (!$occ || !$this->registrationOpenWithSeats($occ, $now)) {
                continue;
            }

            $this->line("repeat: occurrence {$occ->id}, channel {$r->channel_id}, каждые {$r->repeat_interval_days} дн.");
            if (!$dryRun) {
                RepeatOccurrenceAnnouncementJob::dispatch((int) $occ->id, (int) $r->channel_id);
            }
            $dispatched++;
        }

        $this->info(($dryRun ? 'Dry-run: ' : '') . "{$dispatched} repeat job(s)");

        return self::SUCCESS;
    }

    private function registrationOpenWithSeats(EventOccurrence $occ, Carbon $now): bool
    {
        if (!$occ->effectiveAllowRegistration()) {
            return false;
        }
        $starts = $occ->effectiveRegistrationStartsAt();
        if ($starts && $now->lessThan($starts)) {
            return false;
        }
        $ends = $occ->effectiveRegistrationEndsAt();
        if ($ends && $now->greaterThanOrEqualTo($ends)) {
            return false;
        }

        // свободные места: у командных турниров вместимость в игроках не задана — там проверяем только окно регистрации
        $max = (int) ($occ->max_players ?? ($occ->event?->gameSettings?->max_players ?? 0));
        if ($max > 0) {
            $booked = DB::table('event_registrations')
                ->where('occurrence_id', $occ->id)
                ->whereNull('cancelled_at')
                ->where(fn ($q) => $q->whereNull('status')->orWhere('status', 'confirmed'))
                ->count();
            if ($booked >= $max) {
                return false;
            }
        }

        return true;
    }
}
