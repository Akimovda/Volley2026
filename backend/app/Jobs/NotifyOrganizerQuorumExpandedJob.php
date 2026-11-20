<?php

namespace App\Jobs;

use App\Models\EventOccurrence;
use App\Services\UserNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Авто увеличение кворума (docs/auto-quorum.md): уведомление организатору и его помощникам
 * «Игра {дата, время} расширена до N команд — проверьте площадку». Тип event_quorum_expanded,
 * каналы — как у уведомлений о записи (UserNotificationService::organizerRegistrationChannels).
 * Уходит независимо от флага notify_player_registrations: это не о конкретном игроке, а о площадке.
 */
class NotifyOrganizerQuorumExpandedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $occurrenceId,
        public readonly int $fromTeams,
        public readonly int $toTeams,
        public readonly int $promotedCount = 0,
    ) {
    }

    public function handle(UserNotificationService $notifications): void
    {
        $occ = EventOccurrence::with(['event.gameSettings'])->find($this->occurrenceId);
        $event = $occ?->event;
        if (!$occ || !$event || !$event->organizer_id) {
            return;
        }

        $tz     = $occ->timezone ?: ($event->timezone ?: 'Europe/Moscow');
        $starts = Carbon::parse($occ->starts_at, 'UTC')->setTimezone($tz);
        $isBeach = (string) $event->direction === 'beach';

        $title = __('events.auto_quorum_notify_title', [
            'date'  => $starts->format('d.m.Y H:i'),
            'teams' => $this->toTeams,
            'unit'  => $isBeach ? __('events.auto_quorum_unit_pairs') : __('events.auto_quorum_unit_teams'),
        ], 'ru');
        $body = __('events.auto_quorum_notify_body', [
            'title'    => (string) $event->title,
            'from'     => $this->fromTeams,
            'to'       => $this->toTeams,
            'promoted' => $this->promotedCount,
        ], 'ru');

        $staff = DB::table('organizer_staff')->where('organizer_id', $event->organizer_id)->pluck('staff_user_id')->all();
        $recipients = array_values(array_unique(array_map('intval', array_merge([(int) $event->organizer_id], $staff))));

        foreach ($recipients as $userId) {
            try {
                $notifications->create(
                    userId:   $userId,
                    type:     'event_quorum_expanded',
                    title:    $title,
                    body:     $body,
                    payload:  [
                        'event_id'      => (int) $event->id,
                        'occurrence_id' => (int) $occ->id,
                        'from_teams'    => $this->fromTeams,
                        'to_teams'      => $this->toTeams,
                        'button_text'   => __('events.auto_quorum_notify_button', [], 'ru'),
                        'button_url'    => route('events.registrations.index', ['event' => (int) $event->id]) . '?occurrence=' . (int) $occ->id,
                    ],
                    channels: $notifications->organizerRegistrationChannels($userId),
                );
            } catch (\Throwable $e) {
                Log::warning('NotifyOrganizerQuorumExpandedJob: create failed', [
                    'occurrence_id' => $this->occurrenceId,
                    'recipient_id'  => $userId,
                    'error'         => $e->getMessage(),
                ]);
            }
        }
    }
}
