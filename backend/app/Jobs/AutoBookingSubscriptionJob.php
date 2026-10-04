<?php
namespace App\Jobs;

use App\Models\Subscription;
use App\Models\SubscriptionAutoBooking;
use App\Models\EventOccurrence;
use App\Models\EventRegistration;
use App\Models\User;
use App\Services\EventRoleSlotService;
use App\Services\OccurrenceCapacityService;
use App\Services\SubscriptionService;
use App\Services\UserNotificationService;
use App\Services\EventRegistrationGuard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Автозапись по абонементу при открытии регистрации на тур.
 *
 * Источник — таблица subscription_auto_bookings (игрок сам выбирает мероприятие и,
 * для классики с амплуа, ОБЯЗАТЕЛЬНО позицию). Без позиции автозапись на такое
 * мероприятие не срабатывает. Порядок обработки детерминирован: по id записи
 * автозаписи (кто раньше настроил — тот раньше записывается).
 */
class AutoBookingSubscriptionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private int $occurrenceId
    ) {}

    public function handle(
        SubscriptionService $subService,
        UserNotificationService $notificationService
    ): void {
        $occurrence = EventOccurrence::with('event')->find($this->occurrenceId);
        if (!$occurrence || !$occurrence->event || $occurrence->isCancelled()) return;

        $event = $occurrence->event;
        $slotService = app(EventRoleSlotService::class);
        $needsPosition = $slotService->requiresPositionChoice($event);
        $mainRoles = $slotService->mainRoles($event);

        $autoBookings = SubscriptionAutoBooking::with(['subscription.template', 'user'])
            ->where('event_id', $event->id)
            ->orderBy('id')
            ->get();

        foreach ($autoBookings as $ab) {
            $sub = $ab->subscription;
            $user = $ab->user;
            if (!$sub || !$user || $user->is_bot) continue;

            if (!$this->subscriptionUsable($sub, $event->id)) continue;

            $fail = function (string $reason) use ($notificationService, $user, $event, $occurrence) {
                $notificationService->create(
                    userId: $user->id,
                    type: 'auto_booking_failed',
                    title: '⚠️ Автозапись не удалась',
                    body: "Не удалось записать вас на {$event->title}: {$reason}",
                    payload: ['event_id' => $event->id, 'occurrence_id' => $occurrence->id],
                    channels: ['in_app', 'telegram', 'vk', 'max'],
                );
            };

            try {
                // Классика с амплуа: без выбранной позиции автозапись не работает.
                $position = $ab->position ?: null;
                if ($needsPosition) {
                    if (!$position || !in_array($position, $mainRoles, true)) {
                        $fail('не выбрана позиция (амплуа). Откройте «Мои абонементы» и выберите позицию для автозаписи на это мероприятие.');
                        continue;
                    }
                } elseif ($position === null && count($mainRoles) === 1) {
                    $position = $mainRoles[0]; // пляжка: единственная роль player
                }

                $alreadyRegistered = EventRegistration::where('user_id', $user->id)
                    ->where('occurrence_id', $occurrence->id)
                    ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
                    ->whereNull('cancelled_at')
                    ->exists();
                if ($alreadyRegistered) continue;

                $guard = app(EventRegistrationGuard::class);
                $result = $guard->check($user, $occurrence, $position ? ['position' => $position] : []);
                if (!$result->allowed) {
                    $fail(implode(', ', $result->errors));
                    continue;
                }

                // Запись под advisory lock + живой пересчёт вместимости внутри транзакции
                // (см. EventRegistrationController::persistRegistration() и PremiumAutoBookingJob::persist()).
                try {
                    $this->persist($sub, $occurrence, $user, $position, $subService);
                } catch (\RuntimeException $e) {
                    $fail($e->getMessage());
                    continue;
                }

                $notificationService->create(
                    userId: $user->id,
                    type: 'auto_booking_created',
                    title: '🎫 Автозапись по абонементу',
                    body: "Вы записаны на {$event->title} по абонементу. Подтвердите участие за 12 часов до начала.",
                    payload: [
                        'event_id'        => $event->id,
                        'occurrence_id'   => $occurrence->id,
                        'subscription_id' => $sub->id,
                        'confirm_before'  => $occurrence->starts_at
                            ? \Carbon\Carbon::parse($occurrence->starts_at)->subHours(12)->toDateTimeString()
                            : null,
                    ],
                    channels: ['in_app', 'telegram', 'vk', 'max'],
                );

                Log::info("AutoBooking: user #{$user->id} → occurrence #{$occurrence->id} via subscription #{$sub->id}, position=" . ($position ?? '-'));

            } catch (\Throwable $e) {
                Log::error("AutoBooking error: user #{$user->id}, sub #{$sub->id}: " . $e->getMessage());
            }
        }
    }

    private function subscriptionUsable(Subscription $sub, int $eventId): bool
    {
        $sub->loadMissing('template');
        if (!$sub->template || !$sub->template->auto_booking_enabled) return false;

        return $sub->isUsableForEvent($eventId);
    }

    private function persist(
        Subscription $sub,
        EventOccurrence $occurrence,
        User $user,
        ?string $position,
        SubscriptionService $subService
    ): EventRegistration {
        $reg = null;

        DB::transaction(function () use ($sub, $occurrence, $user, $position, $subService, &$reg) {
            // Формула та же, что в persistRegistration()/PremiumAutoBookingJob/WaitlistService:
            // roleKey = позиция ? crc32(позиция) & 0x7fffffff : 0.
            $roleKey = $position ? (crc32($position) & 0x7fffffff) : 0;
            DB::select('SELECT pg_advisory_xact_lock(?, ?)', [$occurrence->id, $roleKey]);

            $existing = EventRegistration::query()
                ->where('user_id', $user->id)
                ->where('occurrence_id', $occurrence->id)
                ->lockForUpdate()
                ->first();

            if ($existing && !$existing->is_cancelled && $existing->cancelled_at === null && $existing->status !== 'cancelled') {
                throw new \RuntimeException('Вы уже записаны на это мероприятие.');
            }

            if (!app(OccurrenceCapacityService::class)->hasRoom($occurrence)) {
                throw new \RuntimeException('Свободных мест на этом мероприятии больше нет.');
            }

            if ($position && !app(EventRoleSlotService::class)->tryTakeSlot($occurrence->event, $position, $occurrence->id)) {
                throw new \RuntimeException('Свободных мест на выбранной позиции больше нет.');
            }

            // Свойства + save(), а не create([...]): subscription_id/auto_booked/payment_status/
            // is_cancelled не входят в $fillable EventRegistration — mass-assignment молча их отбросил бы.
            $reg = $existing ?: new EventRegistration();
            if (!$existing) {
                $reg->user_id = $user->id;
                $reg->event_id = $occurrence->event_id;
                $reg->occurrence_id = $occurrence->id;
            }
            // Для реактивации ранее отменённой регистрации — как Premium и persistRegistration().
            $reg->status = 'confirmed';
            $reg->is_cancelled = false;
            $reg->cancelled_at = null;
            $reg->position = $position;
            $reg->confirmed_at = null;
            $reg->premium_auto_booking_id = null;
            $reg->premium_auto_confirm_deadline_at = null;
            $reg->payment_status = 'subscription';
            $reg->subscription_id = $sub->id;
            $reg->auto_booked = true;
            $reg->save();

            $usage = $subService->useVisit($sub, $occurrence, $reg->id);
            $reg->subscription_usage_id = $usage->id;
            $reg->save();
        });

        return $reg;
    }
}
