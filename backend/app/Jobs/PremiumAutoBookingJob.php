<?php

namespace App\Jobs;

use App\Models\EventOccurrence;
use App\Models\EventRegistration;
use App\Models\PremiumAutoBooking;
use App\Models\User;
use App\Services\EventRegistrationGuard;
use App\Services\EventRoleSlotService;
use App\Services\PaymentService;
use App\Services\PremiumService;
use App\Services\SubscriptionService;
use App\Services\UserNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PremiumAutoBookingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private int $occurrenceId)
    {
    }

    public function handle(
        EventRegistrationGuard $guard,
        PremiumService $premiumService,
        SubscriptionService $subscriptionService,
        UserNotificationService $notificationService
    ): void {
        $occurrence = EventOccurrence::with('event')->find($this->occurrenceId);
        if (!$occurrence || !$occurrence->event || $occurrence->isCancelled()) {
            return;
        }

        $event = $occurrence->event;

        $jobs = PremiumAutoBooking::with('user')
            ->where('event_id', $event->id)
            ->get();

        foreach ($jobs as $autoBooking) {
            $user = $autoBooking->user;
            if (!$user || ($user->is_bot ?? false)) {
                continue;
            }

            try {
                if (!$premiumService->isPremium($user)) {
                    continue;
                }

                // Приоритет у абонемента: если на это мероприятие есть активный
                // абонемент с авто-записью, его обрабатывает AutoBookingSubscriptionJob —
                // Premium-автозапись здесь не участвует, независимо от порядка
                // выполнения обеих команд в один и тот же тик планировщика.
                if ($subscriptionService->hasUsableAutoBookingSubscription($user->id, $event->id)) {
                    continue;
                }

                $alreadyRegistered = EventRegistration::where('user_id', $user->id)
                    ->where('occurrence_id', $occurrence->id)
                    ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
                    ->exists();
                if ($alreadyRegistered) {
                    continue;
                }

                $result = $guard->check($user, $occurrence, ['position' => $autoBooking->position]);
                if (!$result->allowed) {
                    $this->notifyFailed($notificationService, $user, $event, $occurrence, implode(' ', $result->errors));
                    continue;
                }

                try {
                    $reg = $this->persist($user, $occurrence, $autoBooking);
                } catch (\RuntimeException $e) {
                    $this->notifyFailed($notificationService, $user, $event, $occurrence, $e->getMessage());
                    continue;
                }

                $eventUrl = route('events.show', ['event' => $event->id, 'occurrence' => $occurrence->id]);

                $notificationService->create(
                    userId: $user->id,
                    type: 'premium_auto_booking_created',
                    title: '🤖 Автозапись Premium выполнена',
                    body: "Вы записаны на «{$event->title}». Подтвердите участие в течение 5 часов, иначе запись будет автоматически отменена.",
                    payload: [
                        'event_id'         => $event->id,
                        'occurrence_id'    => $occurrence->id,
                        'registration_id'  => $reg->id,
                        'confirm_before'   => $reg->premium_auto_confirm_deadline_at?->toDateTimeString(),
                        'button_url'       => $eventUrl,
                        'button_text'      => 'Подтвердить участие',
                    ],
                    channels: ['in_app', 'telegram', 'vk', 'max'],
                );

                Log::info("PremiumAutoBooking: user #{$user->id} -> occurrence #{$occurrence->id} via job #{$autoBooking->id}");
            } catch (\Throwable $e) {
                Log::error("PremiumAutoBooking error: user #{$user->id}, job #{$autoBooking->id}: " . $e->getMessage());
            }
        }
    }

    private function notifyFailed(
        UserNotificationService $notificationService,
        User $user,
        $event,
        EventOccurrence $occurrence,
        string $reason
    ): void {
        $notificationService->create(
            userId: $user->id,
            type: 'premium_auto_booking_failed',
            title: '⚠️ Автозапись Premium не выполнена',
            body: "Не удалось записать вас на «{$event->title}»: {$reason}",
            payload: [
                'event_id'      => $event->id,
                'occurrence_id' => $occurrence->id,
            ],
            channels: ['in_app', 'telegram', 'vk', 'max'],
        );
    }

    private function persist(User $user, EventOccurrence $occurrence, PremiumAutoBooking $autoBooking): EventRegistration
    {
        $reg = null;

        DB::transaction(function () use ($user, $occurrence, $autoBooking, &$reg) {
            $position = $autoBooking->position;
            $roleKey = crc32($position) & 0x7fffffff;

            DB::select('SELECT pg_advisory_xact_lock(?, ?)', [$occurrence->id, $roleKey]);

            $existing = EventRegistration::query()
                ->where('user_id', $user->id)
                ->where('occurrence_id', $occurrence->id)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $existing->status = 'confirmed';
                $existing->is_cancelled = false;
                $existing->cancelled_at = null;
                $existing->position = $position;
                $existing->auto_booked = true;
                $existing->premium_auto_booking_id = $autoBooking->id;
                $existing->premium_auto_confirm_deadline_at = now()->addHours(5);
                $existing->confirmed_at = null;
                $existing->payment_status = null;
                $existing->payment_id = null;
                $existing->payment_expires_at = null;
                $existing->save();
                $reg = $existing;
            } else {
                $slotService = app(EventRoleSlotService::class);
                if (!$slotService->tryTakeSlot($occurrence->event, $position, $occurrence->id)) {
                    throw new \RuntimeException('Свободных мест на этой позиции больше нет.');
                }

                $reg = new EventRegistration();
                $reg->user_id = $user->id;
                $reg->event_id = $occurrence->event_id;
                $reg->occurrence_id = $occurrence->id;
                $reg->status = 'confirmed';
                $reg->is_cancelled = false;
                $reg->position = $position;
                $reg->auto_booked = true;
                $reg->premium_auto_booking_id = $autoBooking->id;
                $reg->premium_auto_confirm_deadline_at = now()->addHours(5);
                $reg->save();
            }

            $event = $occurrence->event;
            if ($event->is_paid && $event->price_minor > 0) {
                $payment = app(PaymentService::class)->createForRegistration($reg, $event, $occurrence);

                if ($event->payment_method === 'yoomoney') {
                    $reg->payment_status = 'pending';
                    $reg->payment_id = $payment->id;
                    $reg->payment_expires_at = $payment->expires_at;
                } elseif (in_array($event->payment_method, ['tbank_link', 'sber_link'], true)) {
                    $reg->payment_status = 'link_pending';
                    $reg->payment_id = $payment->id;
                } else {
                    $reg->payment_status = 'free';
                }
                $reg->save();
            } else {
                $reg->payment_status = 'free';
                $reg->save();
            }
        });

        return $reg;
    }
}
