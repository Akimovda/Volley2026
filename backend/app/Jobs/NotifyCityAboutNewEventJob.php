<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\EventOccurrence;
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
 * ДО первого диспатча, см. EventStoreService::store()). Rate-limit (1/час на
 * пользователя, per type) — здесь, батчем на чанк, перед вызовом create().
 * Уведомления, чья occurrence с тех пор отменена/удалена, в счёт лимита НЕ
 * идут (alreadyNotifiedUserIds()) — иначе организатор, поправивший ошибку
 * (создал новое взамен отменённого мероприятия в течение того же часа),
 * не смог бы известить жителей о верном мероприятии.
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

        $rateLimitHours = (int) config('notifications.new_event_city_notify_rate_limit_hours', 1);
        $alreadyNotifiedIds = $this->alreadyNotifiedUserIds($chunkUsers->pluck('id')->all(), $rateLimitHours);

        $address = $this->buildAddress($event);

        // Разговорный формат, как в анонсах каналов (OccurrenceAnnouncementMessageBuilder):
        // «18 июля, суббота, 09:27» — время в таймзоне города (через effectiveTimezone()).
        $startsLocal = $occurrence->starts_at->copy()->setTimezone($event->location->effectiveTimezone());
        $dateTimeStr = $startsLocal->locale('ru')->translatedFormat('j F, l') . ', ' . $startsLocal->format('H:i');

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

    /**
     * user_id тех, кто уже получал new_event_in_city в пределах rate-limit окна
     * И чья occurrence (из payload прошлого уведомления) всё ещё актуальна —
     * не отменена и не удалена физически.
     */
    private function alreadyNotifiedUserIds(array $userIds, int $rateLimitHours): array
    {
        $recentNotifs = UserNotification::query()
            ->whereIn('user_id', $userIds)
            ->where('type', 'new_event_in_city')
            ->where('created_at', '>=', now()->subHours($rateLimitHours))
            ->get(['user_id', 'payload']);

        if ($recentNotifs->isEmpty()) {
            return [];
        }

        $occurrenceIds = $recentNotifs
            ->map(fn (UserNotification $n) => $n->payload['occurrence_id'] ?? null)
            ->filter()
            ->unique()
            ->values();

        $activeOccurrenceIds = EventOccurrence::query()
            ->whereIn('id', $occurrenceIds)
            ->whereNull('cancelled_at')
            ->pluck('id')
            ->all();

        $result = [];
        foreach ($recentNotifs as $n) {
            $occId = $n->payload['occurrence_id'] ?? null;
            if ($occId !== null && !in_array($occId, $activeOccurrenceIds, true)) {
                // occurrence отменена или физически удалена — не считаем за лимит
                continue;
            }
            $result[] = $n->user_id;
        }

        return array_values(array_unique($result));
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
