<?php
namespace App\Jobs;

use App\Models\Subscription;
use App\Models\EventOccurrence;
use App\Models\EventRegistration;
use App\Models\User;
use App\Services\SubscriptionService;
use App\Services\UserNotificationService;
use App\Http\Controllers\EventRegistrationGuard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        if (!$occurrence || !$occurrence->event) return;

        $event = $occurrence->event;

        // Находим все активные абонементы с автозаписью на это мероприятие
        $subscriptions = Subscription::with(['user', 'template'])
            ->where('status', 'active')
            ->where('auto_booking', true)
            ->where('visits_remaining', '>', 0)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>=', now()->toDateString());
            })
            ->get()
            ->filter(function ($sub) use ($event) {
                // Проверяем автозапись на конкретные мероприятия
                if (!empty($sub->auto_booking_event_ids)) {
                    if (!in_array($event->id, $sub->auto_booking_event_ids)) return false;
                }
                // Проверяем что абонемент действует на это мероприятие
                return $sub->template->appliesToEvent($event->id);
            });

        foreach ($subscriptions as $sub) {
            $user = $sub->user;
            if (!$user || $user->is_bot) continue;

            try {
                // Проверяем не записан ли уже
                $alreadyRegistered = EventRegistration::where('user_id', $user->id)
                    ->where('occurrence_id', $occurrence->id)
                    ->where('is_cancelled', false)
                    ->exists();

                if ($alreadyRegistered) continue;

                // Проверяем guard
                $guard = app(EventRegistrationGuard::class);
                $result = $guard->check($user, $occurrence, []);
                if (!$result->allowed) {
                    // Уведомляем об ошибке
                    $notificationService->create(
                        userId: $user->id,
                        type: 'auto_booking_failed',
                        title: '⚠️ Автозапись не удалась',
                        body: "Не удалось записать вас на {$event->title}: " . implode(', ', $result->errors),
                        payload: ['event_id' => $event->id, 'occurrence_id' => $occurrence->id],
                        channels: ['in_app', 'telegram', 'vk', 'max'],
                    );
                    continue;
                }

                // Записываем — под advisory lock + живой пересчёт вместимости
                // внутри транзакции (см. EventRegistrationController::persistRegistration()
                // и PremiumAutoBookingJob::persist()). guard->check() выше — обычный
                // COUNT без блокировки; между ним и вставкой ниже параллельный процесс
                // (прямая веб-запись игрока, тот же джоб для другого абонемента при
                // будущем увеличении numprocs воркера) мог бы занять то же место —
                // классический TOCTOU. roleKey=0 — та же формула, что для пустой
                // позиции в persistRegistration()/WaitlistService::autoBookNext()
                // (автозапись по абонементу никогда не выбирает конкретную роль).
                try {
                    $this->persist($sub, $occurrence, $user, $subService);
                } catch (\RuntimeException $e) {
                    $notificationService->create(
                        userId: $user->id,
                        type: 'auto_booking_failed',
                        title: '⚠️ Автозапись не удалась',
                        body: "Не удалось записать вас на {$event->title}: " . $e->getMessage(),
                        payload: ['event_id' => $event->id, 'occurrence_id' => $occurrence->id],
                        channels: ['in_app', 'telegram', 'vk', 'max'],
                    );
                    continue;
                }

                // Уведомляем — нужно подтверждение за 12 часов
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

                Log::info("AutoBooking: user #{$user->id} → occurrence #{$occurrence->id} via subscription #{$sub->id}");

            } catch (\Throwable $e) {
                Log::error("AutoBooking error: user #{$user->id}, sub #{$sub->id}: " . $e->getMessage());
            }
        }
    }

    private function persist(
        Subscription $sub,
        EventOccurrence $occurrence,
        User $user,
        SubscriptionService $subService
    ): EventRegistration {
        $reg = null;

        DB::transaction(function () use ($sub, $occurrence, $user, $subService, &$reg) {
            // roleKey=0 — автозапись по абонементу не выбирает конкретную роль/позицию.
            DB::select('SELECT pg_advisory_xact_lock(?, ?)', [$occurrence->id, 0]);

            $existing = EventRegistration::query()
                ->where('user_id', $user->id)
                ->where('occurrence_id', $occurrence->id)
                ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
                ->lockForUpdate()
                ->first();

            if ($existing) {
                throw new \RuntimeException('Вы уже записаны на это мероприятие.');
            }

            $event = $occurrence->event;
            $event->loadMissing('gameSettings');
            $maxPlayers = (int) ($event->gameSettings?->max_players ?? 0);

            if ($maxPlayers > 0) {
                $registered = EventRegistration::query()
                    ->where('occurrence_id', $occurrence->id)
                    ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
                    ->count();

                if ($registered >= $maxPlayers) {
                    throw new \RuntimeException('Свободных мест на этой мероприятие больше нет.');
                }
            }

            $reg = EventRegistration::create([
                'user_id'        => $user->id,
                'event_id'       => $occurrence->event_id,
                'occurrence_id'  => $occurrence->id,
                'status'         => 'confirmed',
                'is_cancelled'   => false,
                'payment_status' => 'subscription',
                'subscription_id' => $sub->id,
                'auto_booked'    => true,
            ]);

            $usage = $subService->useVisit($sub, $occurrence, $reg->id);
            $reg->update(['subscription_usage_id' => $usage->id]);
        });

        return $reg;
    }
}
