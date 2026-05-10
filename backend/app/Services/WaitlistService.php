<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\EventRegistration;
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

        // Турниры — оставляем старую логику с уведомлением (командная регистрация ≠ позиции)
        $event = $occurrence->event ?: $occurrence->loadMissing('event')->event;
        if ($event && (string) $event->format === 'tournament') {
            $this->notifyNext($occurrence->id, $position);
            return;
        }

        // Индивидуальная регистрация — автозапись первого подходящего из очереди.
        // Один вызов onSpotFreed соответствует освобождению одной позиции,
        // поэтому достаточно одного autoBookNext (массовая отмена даст N вызовов).
        $this->autoBookNext($occurrence, $position);
    }

    /*
    |--------------------------------------------------------------------------
    | AUTO-BOOK NEXT — автоматическая запись из листа ожидания на позицию
    |--------------------------------------------------------------------------
    | Возвращает true если кого-то записали; false — никто не подошёл и место
    | остаётся свободным для общей записи.
    */
    public function autoBookNext(EventOccurrence $occurrence, string $position = ''): bool
    {
        $event = $occurrence->event ?: $occurrence->loadMissing('event')->event;
        if (!$event) return false;

        // Турниры в эту ветку не идут — защита на случай прямого вызова
        if ((string) $event->format === 'tournament') {
            return false;
        }

        $direction = (string) ($event->direction ?? 'classic');

        return DB::transaction(function () use ($occurrence, $event, $position, $direction) {
            $now = now();

            // Очередь: премиум первыми, затем по created_at; lockForUpdate против гонок
            $entries = OccurrenceWaitlist::query()
                ->from('occurrence_waitlist')
                ->where('occurrence_waitlist.occurrence_id', $occurrence->id)
                ->leftJoin('premium_subscriptions', function ($join) use ($now) {
                    $join->on('premium_subscriptions.user_id', '=', 'occurrence_waitlist.user_id')
                         ->where('premium_subscriptions.status', 'active')
                         ->where('premium_subscriptions.expires_at', '>', $now);
                })
                ->orderByRaw('CASE WHEN premium_subscriptions.id IS NOT NULL THEN 0 ELSE 1 END')
                ->orderBy('occurrence_waitlist.created_at')
                ->select('occurrence_waitlist.*')
                ->lockForUpdate()
                ->get();

            $guard    = app(EventRegistrationGuard::class);
            $attempts = 0;

            foreach ($entries as $entry) {
                if ($attempts >= 20) {
                    Log::warning("Waitlist autoBook: достигнут лимит 20 итераций", [
                        'occurrence' => $occurrence->id,
                        'position'   => $position,
                    ]);
                    break;
                }
                $attempts++;

                // Позиция должна подходить (для пляжки positions=[] → подходит любая)
                if (!$entry->subscribedToPosition($position)) {
                    continue;
                }

                $user = $entry->user;
                if (!$user) {
                    $entry->delete();
                    continue;
                }

                // Уже записан в этом occurrence (страховка)
                $alreadyRegistered = EventRegistration::query()
                    ->where('user_id', $user->id)
                    ->where('occurrence_id', $occurrence->id)
                    ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
                    ->whereRaw("(status IS NULL OR status != 'cancelled')")
                    ->exists();
                if ($alreadyRegistered) {
                    $entry->delete();
                    continue;
                }

                // Eligibility (без проверки мест) — уровень/возраст/гендер/окно
                $result = $guard->checkEligibility($user, $occurrence);
                if (!$result->allowed) {
                    Log::info("Waitlist autoBook skip: user #{$user->id} not eligible", [
                        'occurrence' => $occurrence->id,
                        'errors'     => $result->errors,
                    ]);
                    continue;
                }

                // Захват слота для классических основных позиций
                $isReserve = $position === 'reserve';
                if ($direction === 'classic' && $position !== '' && !$isReserve) {
                    $ok = app(EventRoleSlotService::class)->tryTakeSlot($event, $position, $occurrence->id);
                    if (!$ok) {
                        // Слот занят — место уже не наше, выходим
                        return false;
                    }
                }

                // Резервная позиция: проверяем лимит резерва
                if ($isReserve) {
                    $reserveMax = (int) ($event->gameSettings?->reserve_players_max ?? 0);
                    if ($reserveMax <= 0) return false;
                    $reserveTaken = DB::table('event_registrations')
                        ->where('occurrence_id', $occurrence->id)
                        ->where('position', 'reserve')
                        ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
                        ->whereRaw("(status IS NULL OR status != 'cancelled')")
                        ->count();
                    if ($reserveTaken >= $reserveMax) return false;
                }

                // Пляжка: проверка по общему лимиту
                if ($direction === 'beach') {
                    $maxPlayers = (int) ($event->gameSettings?->max_players ?? 0);
                    $registered = DB::table('event_registrations')
                        ->where('occurrence_id', $occurrence->id)
                        ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
                        ->whereRaw("(status IS NULL OR status != 'cancelled')")
                        ->count();
                    if ($maxPlayers > 0 && $registered >= $maxPlayers) return false;
                }

                // Создаём регистрацию — через свойства, чтобы записать поля вне $fillable
                // (auto_booked, is_cancelled, payment_status и т.п.)
                $reg = new EventRegistration();
                $reg->user_id       = $user->id;
                $reg->event_id      = $occurrence->event_id;
                $reg->occurrence_id = $occurrence->id;
                $reg->status        = 'confirmed';
                $reg->is_cancelled  = false;
                $reg->position      = $position !== '' ? $position : null;
                $reg->auto_booked   = true;
                $reg->save();

                // Платное мероприятие — создаём платёж с окном оплаты
                if (!empty($event->is_paid) && (int) ($event->price_minor ?? 0) > 0) {
                    $payment = app(PaymentService::class)->createForRegistration($reg, $event, $occurrence);

                    if ($event->payment_method === 'yoomoney') {
                        $reg->payment_status     = 'pending';
                        $reg->payment_id         = $payment->id;
                        $reg->payment_expires_at = $payment->expires_at;
                        $reg->save();
                    } elseif (in_array($event->payment_method, ['tbank_link', 'sber_link'], true)) {
                        $reg->payment_status = 'link_pending';
                        $reg->payment_id     = $payment->id;
                        $reg->save();
                    }
                }

                // EventRegistrationObserver::created() сам удалит entry,
                // но делаем явно — это идемпотентно и надёжнее в транзакции
                $entry->delete();

                // Уведомление: «Вы записаны из резерва в основной состав»
                app(UserNotificationService::class)->createWaitlistAutoBookedNotification(
                    userId: $user->id,
                    eventId: $event->id,
                    occurrenceId: $occurrence->id,
                    eventTitle: $event->title,
                    position: $position
                );

                Log::info("Waitlist auto-booked: user #{$user->id} → occurrence #{$occurrence->id} pos='{$position}'");

                return true;
            }

            return false;
        });
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
        // Premium-пользователи идут первыми в очереди
        $now = now();
        $entry = OccurrenceWaitlist::query()
            ->where('occurrence_id', $occurrenceId)
            ->where(function ($q) {
                $q->whereNull('notification_expires_at')
                  ->orWhere('notification_expires_at', '<', now());
            })
            ->leftJoin('premium_subscriptions', function ($join) use ($now) {
                $join->on('premium_subscriptions.user_id', '=', 'occurrence_waitlist.user_id')
                     ->where('premium_subscriptions.status', 'active')
                     ->where('premium_subscriptions.expires_at', '>', $now);
            })
            ->orderByRaw('CASE WHEN premium_subscriptions.id IS NOT NULL THEN 0 ELSE 1 END')
            ->orderBy('occurrence_waitlist.created_at')
            ->select('occurrence_waitlist.*')
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

        // Резервные места (классика и пляжка)
        if ($position === 'reserve') {
            $reserveMax = (int)(DB::table('event_game_settings')
                ->where('event_id', $event->id)
                ->value('reserve_players_max') ?? 0);
            if ($reserveMax <= 0) return false;
            $reserveTaken = DB::table('event_registrations')
                ->where('occurrence_id', $occurrence->id)
                ->where('position', 'reserve')
                ->whereNull('cancelled_at')
                ->where(fn($q) => $q->whereNull('status')->orWhere('status', 'confirmed'))
                ->count();
            return $reserveTaken < $reserveMax;
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
            'reserve'  => 'Резерв',
            default    => $key,
        };
    }
}
