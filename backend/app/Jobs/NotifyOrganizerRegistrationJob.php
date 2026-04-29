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

class NotifyOrganizerRegistrationJob implements ShouldQueue
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
    ];

    public function __construct(
        public readonly int $occurrenceId,
        public readonly int $playerId,
        public readonly string $type, // 'registered' | 'cancelled'
    ) {}

    public function handle(UserNotificationService $notificationService): void
    {
        $occurrence = EventOccurrence::with(['event.organizer', 'event.location.city', 'event.gameSettings'])->find($this->occurrenceId);

        if (!$occurrence) {
            return;
        }

        $organizer = $occurrence->event?->organizer;

        if (!$organizer || !$organizer->notify_player_registrations) {
            return;
        }

        $player = User::find($this->playerId);

        if (!$player) {
            return;
        }

        $notificationType = $this->type === 'registered'
            ? 'organizer_player_registered'
            : 'organizer_player_cancelled';

        $fallbackTitle = $this->type === 'registered'
            ? '✅ Регистрация подтверждена'
            : '⛔️ Бронь отменена';

        try {
            $notificationService->create(
                userId:   (int) $organizer->id,
                type:     $notificationType,
                title:    $fallbackTitle,
                body:     null,
                payload:  array_merge(
                    ['event_id' => (int) $occurrence->event->id, 'occurrence_id' => (int) $occurrence->id],
                    $this->buildExtra($occurrence, $player)
                ),
                channels: ['in_app', 'telegram', 'vk', 'max'],
            );
        } catch (\Throwable $e) {
            Log::warning('NotifyOrganizerRegistrationJob: create failed', [
                'occurrence_id' => $this->occurrenceId,
                'player_id'     => $this->playerId,
                'type'          => $this->type,
                'error'         => $e->getMessage(),
            ]);
        }
    }

    private function buildExtra(EventOccurrence $occurrence, User $player): array
    {
        $event = $occurrence->event;

        $playerName = trim(implode(' ', array_filter([
            $player->last_name,
            $player->first_name,
            $player->patronymic,
        ]))) ?: ((string) ($player->name ?? ''));

        $positionCode = DB::table('event_registrations')
            ->where('user_id', $player->id)
            ->where('occurrence_id', $occurrence->id)
            ->whereNull('cancelled_at')
            ->value('position');
        $playerPosition = $positionCode ? (self::POS_LABELS[$positionCode] ?? $positionCode) : '';

        $booked = (int) DB::table('event_registrations')
            ->where('occurrence_id', $occurrence->id)
            ->whereNull('cancelled_at')
            ->where(fn ($q) => $q->whereNull('status')->orWhere('status', 'confirmed'))
            ->count();
        $maxPlayers = (int) ($occurrence->max_players ?? $event->gameSettings?->max_players ?? 0);
        $available = $maxPlayers > 0 ? max(0, $maxPlayers - $booked) : 0;

        $tz = $occurrence->timezone ?: ($event->timezone ?: 'UTC');
        $starts = Carbon::parse($occurrence->starts_at, 'UTC')->setTimezone($tz);
        $durationSec = $occurrence->duration_sec ?? $event->duration_sec ?? null;
        $ends = $durationSec ? $starts->copy()->addSeconds((int) $durationSec) : null;

        Carbon::setLocale('ru');
        $eventDate = mb_ucfirst($starts->translatedFormat('l')) . ', ' . $starts->translatedFormat('j F');
        $eventTime = $starts->format('H:i') . ($ends ? ' - ' . $ends->format('H:i') : '');

        return [
            'player_name'     => $playerName,
            'player_phone'    => (string) ($player->phone ?? ''),
            'player_position' => $playerPosition,
            'booked_count'    => (string) $booked,
            'available_count' => (string) $available,
            'event_date'      => $eventDate,
            'event_time'      => $eventTime,
        ];
    }
}
