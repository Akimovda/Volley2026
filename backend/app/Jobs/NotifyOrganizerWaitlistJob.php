<?php

namespace App\Jobs;

use App\Models\EventOccurrence;
use App\Models\User;
use App\Services\UserNotificationService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotifyOrganizerWaitlistJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    private const POS_LABELS = [
        'setter'   => 'Связующий',
        'outside'  => 'Доигровщик',
        'opposite' => 'Диагональный',
        'middle'   => 'Центральный',
        'libero'   => 'Либеро',
        'defender' => 'Защитник',
        'reserve'  => 'Запасной',
        'player'   => 'Игрок',
    ];

    public function __construct(
        public readonly int $occurrenceId,
        public readonly int $playerId,
        public readonly array $positions = [],
    ) {}

    public function handle(UserNotificationService $notificationService): void
    {
        $occurrence = EventOccurrence::with(['event.organizer', 'event.location.city', 'event.gameSettings'])
            ->find($this->occurrenceId);

        if (!$occurrence) return;

        $organizer = $occurrence->event?->organizer;
        if (!$organizer || !$organizer->notify_player_registrations) return;

        $player = User::find($this->playerId);
        if (!$player) return;

        $playerName = trim(implode(' ', array_filter([
            $player->last_name,
            $player->first_name,
        ]))) ?: ((string) ($player->name ?? 'Игрок'));

        $positions = array_values(array_filter($this->positions));
        if (!empty($positions)) {
            $posLabel = implode(', ', array_map(
                fn($p) => self::POS_LABELS[$p] ?? $p,
                $positions
            ));
        } else {
            $posLabel = 'все позиции';
        }

        $waitlistCount = (int) DB::table('occurrence_waitlist')
            ->where('occurrence_id', $this->occurrenceId)
            ->count();

        $event = $occurrence->event;
        $tz    = $occurrence->timezone ?: ($event->timezone ?: 'UTC');
        $starts = Carbon::parse($occurrence->starts_at, 'UTC')->setTimezone($tz);
        Carbon::setLocale('ru');
        $eventDate = mb_ucfirst($starts->translatedFormat('l')) . ', ' . $starts->translatedFormat('j F');
        $eventTime = $starts->format('H:i');

        try {
            $notificationService->create(
                userId:   (int) $organizer->id,
                type:     'organizer_player_waitlisted',
                title:    'Запись в лист ожидания',
                body:     null,
                payload:  [
                    'event_id'       => (int) $event->id,
                    'occurrence_id'  => (int) $occurrence->id,
                    'event_title'    => (string) ($event->title ?? ''),
                    'event_date'     => $eventDate,
                    'event_time'     => $eventTime,
                    'player_name'    => $playerName,
                    'player_phone'   => (string) ($player->phone ?? ''),
                    'pos_label'      => $posLabel,
                    'waitlist_count' => $waitlistCount,
                ],
                channels: ['in_app', 'telegram', 'vk', 'max'],
            );
        } catch (\Throwable $e) {
            Log::warning('NotifyOrganizerWaitlistJob: create failed', [
                'occurrence_id' => $this->occurrenceId,
                'player_id'     => $this->playerId,
                'error'         => $e->getMessage(),
            ]);
        }
    }
}
