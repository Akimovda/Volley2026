<?php
namespace App\Services;

use App\Models\Subscription;
use App\Models\SubscriptionTemplate;
use App\Models\SubscriptionUsage;
use App\Models\SubscriptionCouponLog;
use App\Models\EventOccurrence;
use App\Models\EventRegistration;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    public function __construct(
        private UserNotificationService $notificationService,
        private OrganizerChannelBroadcastService $organizerBroadcast
    ) {}

    /**
     * Если после списания визита у абонемента остался ровно 1 — уведомить
     * игрока (пора купить новый) и организатора во все его каналы (пора продать новый).
     * Срабатывает только на переходе N→1 (естественно один раз на цикл использования).
     */
    public function notifyIfLowVisits(Subscription $subscription): void
    {
        if ($subscription->visits_remaining !== 1) {
            return;
        }

        $subscription->loadMissing('template', 'user');

        $this->notificationService->createSubscriptionLowVisitsNotification($subscription);

        $user = $subscription->user;
        $userName = trim(($user->last_name ?? '') . ' ' . ($user->first_name ?? '')) ?: ($user->email ?? "ID {$user->id}");
        $templateName = $subscription->template->name ?? 'Абонемент';

        $this->organizerBroadcast->sendText(
            organizerId: $subscription->organizer_id,
            title: '⚠️ У игрока заканчивается абонемент',
            text: "У пользователя {$userName} заканчивается абонемент «{$templateName}» — остался последний визит.\n"
                . 'Предложите ему купить новый.',
        );
    }

    /**
     * Выдать абонемент пользователю (вручную или при покупке)
     */
    public function issue(
        SubscriptionTemplate $template,
        int $userId,
        ?int $issuedBy = null,
        string $reason = 'manual',
        ?string $paymentStatus = null
    ): Subscription {
        $sub = DB::transaction(function () use ($template, $userId, $issuedBy, $reason, $paymentStatus) {
            $startsAt = now()->toDateString();
            $expiresAt = null;

            // Приоритет: duration_months/duration_days -> valid_until
            $durationMonths = (int) ($template->duration_months ?? 0);
            $durationDays   = (int) ($template->duration_days ?? 0);

            if ($durationMonths > 0 || $durationDays > 0) {
                $expires = now();
                if ($durationMonths > 0) $expires = $expires->addMonths($durationMonths);
                if ($durationDays > 0)   $expires = $expires->addDays($durationDays);
                $expiresAt = $expires->toDateString();
            } elseif ($template->valid_until) {
                $expiresAt = $template->valid_until->toDateString();
            }

            $sub = Subscription::create([
                'user_id'        => $userId,
                'template_id'    => $template->id,
                'organizer_id'   => $template->organizer_id,
                'starts_at'      => $startsAt,
                'expires_at'     => $expiresAt,
                'visits_total'   => $template->visits_total,
                'visits_used'    => 0,
                'visits_remaining' => $template->visits_total,
                'status'         => 'active',
                'payment_status' => $paymentStatus ?? ($template->price_minor > 0 ? 'pending' : 'free'),
                'issued_by'      => $issuedBy,
                'issue_reason'   => $reason,
            ]);

            // Увеличиваем счётчик продаж
            $template->increment('sold_count');

            SubscriptionCouponLog::write('subscription', $sub->id, 'issued', [
                'template' => $template->name,
                'visits'   => $template->visits_total,
                'reason'   => $reason,
            ], $issuedBy);

            return $sub;
        });

        $this->notifyAutoBookingSetup($sub);

        return $sub;
    }

    /**
     * Мероприятия, на которые игрок может настроить автозапись по этому абонементу:
     * мероприятия организатора абонемента (с учётом event_ids шаблона), индивидуальная
     * запись, есть будущий тур, ещё не настроенные. Для классики с амплуа отдаёт позиции
     * (required=true — выбор обязателен), для пляжки позиция однозначна и не спрашивается.
     */
    public function autoBookingEventsFor(Subscription $sub, ?\App\Models\User $viewer = null): \Illuminate\Support\Collection
    {
        $sub->loadMissing('template');
        $tpl = $sub->template;
        if (!$tpl || !$tpl->auto_booking_enabled) {
            return collect();
        }

        $taken = $sub->autoBookings()->pluck('event_id')->all();

        $query = \App\Models\Event::query()
            ->with('location')
            ->where('organizer_id', $sub->organizer_id)
            ->where('allow_registration', true)
            ->whereNotIn('registration_mode', ['team_classic', 'team_beach'])
            ->where(function ($w) {
                $w->where('format', '!=', 'tournament')
                  ->orWhereNull('format')
                  ->orWhereIn('registration_mode', ['tournament_individual', 'king_beach']);
            })
            ->whereHas('occurrences', function ($oq) {
                $oq->where('starts_at', '>', now())
                    ->whereNull('cancelled_at')
                    ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)');
            });

        if (!empty($tpl->event_ids)) {
            $query->whereIn('id', $tpl->event_ids);
        }
        if ($taken) {
            $query->whereNotIn('id', $taken);
        }

        app(EventVisibilityService::class)->applyPrivateVisibilityScope($query, $viewer);

        $slotService = app(EventRoleSlotService::class);

        return $query->orderByDesc('id')->limit(50)->get()->map(function ($event) use ($slotService) {
            $required = $slotService->requiresPositionChoice($event);
            return [
                'id'        => $event->id,
                'label'     => '#' . $event->id . ' — ' . $event->title
                    . ($event->location ? ' («' . $event->location->name . '»)' : ''),
                'required'  => $required,
                'positions' => $required
                    ? collect($slotService->mainRoles($event))
                        ->map(fn ($r) => ['value' => $r, 'label' => __('events.positions.' . $r)])->values()->all()
                    : [],
            ];
        })->values();
    }

    /**
     * При получении абонемента с автозаписью — сообщить игроку, что автозапись нужно
     * настроить, и что для классики с амплуа обязательно выбрать позицию (без неё
     * автозапись на это мероприятие не работает). Во все каналы, включая push.
     * Сбой уведомления не должен ломать выдачу абонемента.
     */
    private function notifyAutoBookingSetup(Subscription $sub): void
    {
        try {
            $sub->loadMissing('template');
            if (!$sub->template || !$sub->template->auto_booking_enabled) {
                return;
            }

            $this->notificationService->create(
                userId: (int) $sub->user_id,
                type: 'subscription_auto_booking_hint',
                title: '🎫 Абонемент: настройте автозапись',
                body: "По абонементу «{$sub->template->name}» доступна автозапись на мероприятия: "
                    . 'откройте «Мои абонементы», выберите мероприятие, и вы будете записаны автоматически в момент открытия регистрации. '
                    . 'Для классического волейбола (с выбором амплуа) нужно обязательно выбрать позицию, на которую вас будут записывать, '
                    . '— без выбранной позиции автозапись на это мероприятие не сработает.',
                payload: [
                    'subscription_id' => $sub->id,
                    'button_text'     => 'Настроить автозапись',
                    'button_url'      => route('subscriptions.my'),
                ],
                channels: ['in_app', 'telegram', 'vk', 'max', 'push'],
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('SubscriptionService: auto-booking hint failed', [
                'subscription_id' => $sub->id,
                'error'           => $e->getMessage(),
            ]);
        }
    }

    /**
     * Использовать посещение при записи
     */
    public function useVisit(
        Subscription $subscription,
        EventOccurrence $occurrence,
        int $registrationId
    ): SubscriptionUsage {
        $usage = DB::transaction(function () use ($subscription, $occurrence, $registrationId) {
            if (!$subscription->hasVisitsLeft()) {
                throw new \Exception('Нет доступных посещений в абонементе');
            }

            $subscription->decrement('visits_remaining');
            $subscription->increment('visits_used');

            if ($subscription->visits_remaining === 0) {
                $subscription->update(['status' => 'exhausted']);
            }

            $usage = SubscriptionUsage::create([
                'subscription_id' => $subscription->id,
                'user_id'         => $subscription->user_id,
                'event_id'        => $occurrence->event_id,
                'occurrence_id'   => $occurrence->id,
                'registration_id' => $registrationId,
                'action'          => 'used',
                'used_at'         => now(),
            ]);

            SubscriptionCouponLog::write('subscription', $subscription->id, 'used', [
                'occurrence_id' => $occurrence->id,
                'event_id'      => $occurrence->event_id,
            ]);

            return $usage;
        });

        // Уведомления шлём ПОСЛЕ коммита — это внешние HTTP-вызовы (Telegram/VK/MAX),
        // не должны попасть под откат транзакции и не должны её задерживать.
        $this->notifyIfLowVisits($subscription);

        return $usage;
    }

    /**
     * Вернуть посещение при отмене записи
     */
    public function returnVisit(
        Subscription $subscription,
        EventOccurrence $occurrence,
        int $registrationId
    ): void {
        DB::transaction(function () use ($subscription, $occurrence, $registrationId) {
            $template = $subscription->template;
            $cancelHours = $template->cancel_hours_before ?? 0;

            // Определяем — вернуть или сжечь
            $hoursToEvent = now()->diffInHours($occurrence->starts_at, false);
            $action = ($cancelHours > 0 && $hoursToEvent < $cancelHours) ? 'burned' : 'returned';

            $usage = SubscriptionUsage::where('subscription_id', $subscription->id)
                ->where('occurrence_id', $occurrence->id)
                ->where('action', 'used')
                ->latest()
                ->first();

            if ($usage) {
                $usage->update(['action' => $action, 'returned_at' => now()]);
            }

            if ($action === 'returned') {
                $subscription->increment('visits_remaining');
                $subscription->decrement('visits_used');

                // Восстанавливаем статус если был exhausted
                if ($subscription->status === 'exhausted') {
                    $subscription->update(['status' => 'active']);
                }
            }

            SubscriptionCouponLog::write('subscription', $subscription->id, $action, [
                'occurrence_id' => $occurrence->id,
                'hours_to_event' => $hoursToEvent,
            ]);
        });
    }

    /**
     * Заморозить абонемент
     */
    public function freeze(Subscription $subscription, Carbon $until): void
    {
        $template = $subscription->template;

        if (!$template->freeze_enabled) {
            throw new \Exception('Заморозка не разрешена для этого абонемента');
        }

        $subscription->update([
            'status'      => 'frozen',
            'frozen_at'   => now()->toDateString(),
            'frozen_until' => $until->toDateString(),
        ]);

        // Продлеваем срок действия на период заморозки
        if ($subscription->expires_at) {
            $days = now()->diffInDays($until);
            $subscription->update([
                'expires_at' => $subscription->expires_at->addDays($days)->toDateString(),
            ]);
        }

        SubscriptionCouponLog::write('subscription', $subscription->id, 'frozen', [
            'frozen_until' => $until->toDateString(),
        ]);
    }

    /**
     * Разморозить абонемент
     */
    public function unfreeze(Subscription $subscription): void
    {
        $subscription->update([
            'status'       => 'active',
            'frozen_at'    => null,
            'frozen_until' => null,
        ]);

        SubscriptionCouponLog::write('subscription', $subscription->id, 'unfrozen');
    }

    /**
     * Передать абонемент другому пользователю
     */
    public function transfer(Subscription $subscription, int $toUserId): void
    {
        if (!$subscription->template->transfer_enabled) {
            throw new \Exception('Передача не разрешена для этого абонемента');
        }

        $fromUserId = $subscription->user_id;
        $subscription->update(['user_id' => $toUserId]);

        SubscriptionCouponLog::write('subscription', $subscription->id, 'transferred', [
            'from_user_id' => $fromUserId,
            'to_user_id'   => $toUserId,
        ]);
    }

    /**
     * Продлить срок действия
     */
    public function extend(Subscription $subscription, int $days): void
    {
        $newDate = ($subscription->expires_at ?? now())->addDays($days);
        $subscription->update(['expires_at' => $newDate->toDateString()]);

        if ($subscription->status === 'expired') {
            $subscription->update(['status' => 'active']);
        }

        SubscriptionCouponLog::write('subscription', $subscription->id, 'extended', [
            'days'     => $days,
            'new_date' => $newDate->toDateString(),
        ]);
    }

    /**
     * Деактивировать просроченные абонементы
     */
    public function expireOldSubscriptions(): int
    {
        $expired = Subscription::where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now()->toDateString())
            ->get();

        foreach ($expired as $sub) {
            $sub->update(['status' => 'expired']);
            SubscriptionCouponLog::write('subscription', $sub->id, 'expired', null, null);
        }

        // Разморозка автоматическая
        $toUnfreeze = Subscription::where('status', 'frozen')
            ->whereNotNull('frozen_until')
            ->where('frozen_until', '<', now()->toDateString())
            ->get();

        foreach ($toUnfreeze as $sub) {
            $this->unfreeze($sub);
        }

        return $expired->count();
    }

    /**
     * Найти активный абонемент пользователя для мероприятия
     */
    public function findActiveForEvent(int $userId, int $eventId): ?Subscription
    {
        return Subscription::with('template')
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->where('visits_remaining', '>', 0)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()->toDateString());
            })
            ->get()
            ->first(fn($sub) => $sub->template->appliesToEvent($eventId));
    }

    /**
     * Есть ли у игрока абонемент с включённой авто-записью, действующий на это
     * мероприятие — та же выборка, что использует AutoBookingSubscriptionJob.
     * Абонемент имеет приоритет над Premium-автозаписью (PremiumAutoBookingJob
     * пропускает пользователя, если тут вернулось true).
     */
    public function hasUsableAutoBookingSubscription(int $userId, int $eventId): bool
    {
        $event = \App\Models\Event::find($eventId);
        $needsPosition = $event ? app(EventRoleSlotService::class)->requiresPositionChoice($event) : false;

        return \App\Models\SubscriptionAutoBooking::with('subscription.template')
            ->where('user_id', $userId)
            ->where('event_id', $eventId)
            ->get()
            ->contains(function ($ab) use ($eventId, $needsPosition) {
                $sub = $ab->subscription;
                if (!$sub || !$sub->template || !$sub->template->auto_booking_enabled) return false;
                // Без выбранной позиции (классика) абонементная автозапись не сработает —
                // тогда Premium-автозапись не должна уступать ей дорогу.
                if ($needsPosition && !$ab->position) return false;
                return $sub->isUsableForEvent($eventId);
            });
    }
}
