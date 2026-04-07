<?php

namespace App\Services;

use App\Models\EventOccurrence;
use App\Models\OccurrenceWaitlist;
use App\Models\User;
use App\Jobs\CheckWaitlistNotificationJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WaitlistService
{
    // Окно уведомления — 15 минут
    private const NOTIFICATION_WINDOW_MINUTES = 15;

    /*
    |--------------------------------------------------------------------------
    | JOIN WAITLIST
    |--------------------------------------------------------------------------
    */
    public function join(EventOccurrence $occurrence, User $user, array $positions = []): OccurrenceWaitlist
    {
        // Нормализуем позиции
        $positions = array_values(array_unique(array_filter($positions)));

        $entry = OccurrenceWaitlist::updateOrCreate(
            [
                'occurrence_id' => $occurrence->id,
                'user_id'       => $user->id,
            ],
            [
                'positions'                => $positions,
                'notified_at'              => null,
                'notification_expires_at'  => null,
            ]
        );
        // ✅ Уведомление о записи в резерв
        app(\App\Services\UserNotificationService::class)->createWaitlistJoinedNotification(
            userId: $user->id,
            eventId: $occurrence->event->id,
            occurrenceId: $occurrence->id,
            eventTitle: $occurrence->event->title,
            positions: $positions
        );

        Log::info("Waitlist: user #{$user->id} joined occurrence #{$occurrence->id}", [
            'positions' => $positions,
        ]);

        return $entry;
    }

    /*
    |--------------------------------------------------------------------------
    | LEAVE WAITLIST
    |--------------------------------------------------------------------------
    */
    public function leave(EventOccurrence $occurrence, User $user): void
    {
        OccurrenceWaitlist::query()
            ->where('occurrence_id', $occurrence->id)
            ->where('user_id', $user->id)
            ->delete();

        Log::info("Waitlist: user #{$user->id} left occurrence #{$occurrence->id}");
    }

    /*
    |--------------------------------------------------------------------------
    | ON SPOT FREED — вызывается когда место освободилось
    |--------------------------------------------------------------------------
    */
    public function onSpotFreed(EventOccurrence $occurrence, string $position = ''): void
    {
        // Проверяем что мероприятие ещё не началось
        if ($occurrence->starts_at && now('UTC')->gte($occurrence->starts_at)) {
            return;
        }

        // Проверяем что в резерве вообще есть люди на эту позицию
        $hasWaiting = OccurrenceWaitlist::query()
            ->where('occurrence_id', $occurrence->id)
            ->exists();

        if (!$hasWaiting) {
            return;
        }

        $this->notifyNext($occurrence->id, $position);
    }

    /*
    |--------------------------------------------------------------------------
    | NOTIFY NEXT — уведомить следующего в очереди
    |--------------------------------------------------------------------------
    */
    public function notifyNext(int $occurrenceId, string $position): void
    {
        $occurrence = EventOccurrence::with('event')->find($occurrenceId);
        if (!$occurrence) return;

        // Проверяем что место реально свободно
        if (!$this->isSpotAvailable($occurrence, $position)) {
            return;
        }

        // Находим первого подходящего в очереди у кого нет активного окна
        $entry = OccurrenceWaitlist::query()
            ->where('occurrence_id', $occurrenceId)
            ->where(function ($q) {
                $q->whereNull('notification_expires_at')
                  ->orWhere('notification_expires_at', '<', now());
            })
            ->orderBy('created_at')
            ->get()
            ->first(function ($entry) use ($position) {
                return $entry->subscribedToPosition($position);
            });

        if (!$entry) {
            return;
        }

        // Ставим окно уведомления
        $entry->update([
            'notified_at'             => now(),
            'notification_expires_at' => now()->addMinutes(self::NOTIFICATION_WINDOW_MINUTES),
        ]);

        // Отправляем уведомление
        $this->sendNotification($entry->user, $occurrence, $position);

        // Диспатчим джоб — через 15 минут проверить следующего
        CheckWaitlistNotificationJob::dispatch($occurrenceId, $position)
            ->delay(now()->addMinutes(self::NOTIFICATION_WINDOW_MINUTES))
            ->onQueue('default');

        Log::info("Waitlist: notified user #{$entry->user_id} for occurrence #{$occurrenceId} position={$position}");
    }

    /*
    |--------------------------------------------------------------------------
    | REMOVE FROM WAITLIST WHEN REGISTERED
    |--------------------------------------------------------------------------
    */
    public function removeIfRegistered(int $occurrenceId, int $userId): void
    {
        OccurrenceWaitlist::query()
            ->where('occurrence_id', $occurrenceId)
            ->where('user_id', $userId)
            ->delete();
    }

    /*
    |--------------------------------------------------------------------------
    | CHECK SPOT AVAILABLE
    |--------------------------------------------------------------------------
    */
    private function isSpotAvailable(EventOccurrence $occurrence, string $position): bool
    {
        $event = $occurrence->event;
        if (!$event) return false;

        $direction = (string)($event->direction ?? 'classic');

        if ($direction === 'beach') {
            // Для пляжки — проверяем общий лимит
            $maxPlayers = DB::table('event_game_settings')
                ->where('event_id', $event->id)
                ->value('max_players') ?? 0;

            $registered = DB::table('event_registrations')
                ->where('occurrence_id', $occurrence->id)
                ->where('status', 'confirmed')
                ->where(function ($q) {
                    $q->whereNull('is_cancelled')->orWhere('is_cancelled', false);
                })
                ->count();

            return $maxPlayers > 0 && $registered < $maxPlayers;
        }

        // Для классики — проверяем конкретную позицию
        if (!$position) return false;

        $slots = app(EventRoleSlotService::class)->getSlots($event);
        $slot  = collect($slots)->firstWhere('role', $position);

        if (!$slot) return false;

        $taken = DB::table('event_registrations')
            ->where('occurrence_id', $occurrence->id)
            ->where('position', $position)
            ->where('status', 'confirmed')
            ->where(function ($q) {
                $q->whereNull('is_cancelled')->orWhere('is_cancelled', false);
            })
            ->count();

        return $taken < $slot->max_slots;
    }

    /*
    |--------------------------------------------------------------------------
    | SEND NOTIFICATION
    |--------------------------------------------------------------------------
    */
    private function sendNotification(User $user, EventOccurrence $occurrence, string $position): void
    {
        $event = $occurrence->event;
    
        app(\App\Services\UserNotificationService::class)->createWaitlistSpotFreedNotification(
            userId: $user->id,
            eventId: $event->id,
            occurrenceId: $occurrence->id,
            eventTitle: $event->title,
            position: $position
        );
    }

    private function positionLabel(string $key): string
    {
        return match($key) {
            'setter'   => 'Связующий',
            'outside'  => 'Доигровщик',
            'opposite' => 'Диагональный',
            'middle'   => 'Центральный',
            'libero'   => 'Либеро',
            'player'   => 'Игрок',
            default    => $key,
        };
    }
}
