<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\UserNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Рассылка «новое мероприятие в городе» жителям — чанками, цепочкой (каждый job
 * обрабатывает один чанк получателей и диспатчит следующий с новым offset).
 * Дедуп на уровне события — events.city_notified_at (проставляется атомарно
 * ДО первого диспатча, см. EventStoreService::store()). Rate-limit (1/сутки на
 * пользователя, per type) — здесь, батчем на чанк, перед вызовом create().
 */
class NotifyCityAboutNewEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 30;

    public function __construct(
        public readonly int $eventId,
        public readonly int $cityId,
        public readonly int $organizerId,
        public readonly int $offset = 0,
    ) {
        $this->onQueue('broadcasts');
    }

    public function handle(UserNotificationService $notif): void
    {
        if (!config('notifications.new_event_city_notify_enabled', true)) {
            return;
        }

        $event = Event::query()->with(['location.city', 'location'])->find($this->eventId);
        if (!$event) {
            return;
        }

        $occurrence = $event->occurrences()
            ->where('starts_at', '>=', now())
            ->whereNull('cancelled_at')
            ->orderBy('starts_at')
            ->first();

        if (!$occurrence) {
            // Нечего показывать — все occurrences этого события уже в прошлом/отменены
            return;
        }

        $chunkSize = max(1, (int) config('notifications.new_event_city_notify_chunk_size', 75));

        $chunkUsers = User::query()
            ->where('city_id', $this->cityId)
            ->where('is_bot', false)
            ->where('notify_new_events_in_city', true)
            ->where('id', '!=', $this->organizerId)
            ->orderBy('id')
            ->skip($this->offset)
            ->take($chunkSize)
            ->get(['id', 'locale']);

        if ($chunkUsers->isEmpty()) {
            return;
        }

        $rateLimitHours = (int) config('notifications.new_event_city_notify_rate_limit_hours', 24);
        $alreadyNotifiedIds = UserNotification::query()
            ->whereIn('user_id', $chunkUsers->pluck('id'))
            ->where('type', 'new_event_in_city')
            ->where('created_at', '>=', now()->subHours($rateLimitHours))
            ->pluck('user_id')
            ->all();

        $address     = $this->buildAddress($event);
        $dateTimeStr = $occurrence->starts_at
            ->copy()
            ->setTimezone($event->location->effectiveTimezone())
            ->format('d.m.Y H:i');

        foreach ($chunkUsers as $user) {
            if (in_array($user->id, $alreadyNotifiedIds, true)) {
                continue;
            }

            try {
                $notif->createNewEventInCityNotification($user, $event, $occurrence, $address, $dateTimeStr);
            } catch (\Throwable $e) {
                Log::warning('NotifyCityAboutNewEventJob: failed for user', [
                    'user_id'  => $user->id,
                    'event_id' => $this->eventId,
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        // Чанк был полным — возможно, есть ещё получатели, диспатчим следующий.
        if ($chunkUsers->count() === $chunkSize) {
            self::dispatch($this->eventId, $this->cityId, $this->organizerId, $this->offset + $chunkSize)
                ->onQueue('broadcasts');
        }
    }

    private function buildAddress(Event $event): string
    {
        $location = $event->location;
        if (!$location) {
            return '';
        }

        $parts = array_filter([
            $location->metro ?? null,
            $location->city?->name ?? null,
            $location->address ?? null,
        ]);

        return $parts ? implode(', ', $parts) : (string) ($location->name ?? '');
    }

    public function failed(\Throwable $e): void
    {
        Log::error('NotifyCityAboutNewEventJob failed', [
            'event_id' => $this->eventId,
            'offset'   => $this->offset,
            'error'    => $e->getMessage(),
        ]);
    }
}
