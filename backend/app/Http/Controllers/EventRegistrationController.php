<?php
// app/Http/Controllers/EventRegistrationController.php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\EventRegistration;
use App\Models\User;
use App\Services\EventCancellationGuard;
use App\Services\EventOccurrenceStatsService;
use App\Services\EventRegistrationGuard;
use App\Services\EventRegistrationGroupService;
use App\Services\EventRoleSlotService;
use App\Services\UserNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Services\PaymentService;
use App\Services\PlayerFollowService;
use App\Services\SubscriptionService;
use App\Services\CouponService;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Coupon;
use Illuminate\Support\Facades\Schema;

class EventRegistrationController extends Controller
{
    public function __construct(
        private UserNotificationService $userNotificationService,
        private EventRegistrationGroupService $groupService
    ) {}

    /**
     * Legacy: POST /events/{event}/join
     * Чтобы не ломать старые кнопки — записываем в "первый occurrence" события.
     */
    public function store(Request $request, Event $event)
    {
        $occId = (int) ($request->query('occurrence', 0) ?: $request->input('occurrence_id', 0));

        $occ = null;

        if ($occId > 0 && Schema::hasTable('event_occurrences')) {
            $occ = EventOccurrence::query()
                ->where('id', $occId)
                ->where('event_id', (int) $event->id)
                ->first();
        }

        if (!$occ) {
            $occ = $this->getOrCreateFirstOccurrenceForEvent($event);
        }

        if (!$occ) {
            return redirect()
                ->route('events.show', ['event' => (int) $event->id])
                ->with('error', 'Не удалось найти occurrence для события.');
        }

        return $this->storeOccurrence($request, $occ);
    }
    private function dispatchAnnounceUpdate(EventOccurrence $occurrence): void
    {
        $hasChannels = \Illuminate\Support\Facades\DB::table('event_notification_channels')
            ->where('event_id', (int) $occurrence->event_id)
            ->exists();
    
        if (!$hasChannels) {
            return;
        }
    
        \App\Jobs\RefreshOccurrenceAnnouncementJob::dispatch((int) $occurrence->id)
            ->onQueue('default')
            ->afterCommit();
    }
    /**
     * Legacy: DELETE /events/{event}/leave
     * Отписка от "первого occurrence".
     */
    public function destroy(Request $request, Event $event)
    {
        $occId = (int) ($request->query('occurrence', 0) ?: $request->input('occurrence_id', 0));

        $occ = null;

        if ($occId > 0 && Schema::hasTable('event_occurrences')) {
            $occ = EventOccurrence::query()
                ->where('id', $occId)
                ->where('event_id', (int) $event->id)
                ->first();
        }

        if (!$occ) {
            $occ = $this->getOrCreateFirstOccurrenceForEvent($event);
        }

        if (!$occ) {
            return redirect()
                ->route('events.show', ['event' => (int) $event->id])
                ->with('error', 'Не удалось найти occurrence для события.');
        }

        return $this->destroyOccurrence($request, $occ);
    }

    /**
     * NEW: POST /occurrences/{occurrence}/join
     */
    public function storeOccurrence(Request $request, EventOccurrence $occurrence)
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        $event = $occurrence->event;

        // Проверка незаполненных полей профиля до вызова Guard
        $missingFields = $user->getMissingFieldsForEvent($event, $occurrence);
        if (!empty($missingFields)) {
            $returnTo = route('events.show', ['event' => (int) $event->id, 'occurrence' => (int) $occurrence->id]);
            $profileUrl = route('profile.complete') . '?' . http_build_query([
                'missing'   => implode(',', $missingFields),
                'return_to' => $returnTo,
            ]);
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'ok'       => false,
                    'message'  => 'Для записи на мероприятие необходимо заполнить данные в профиле.',
                    'redirect' => $profileUrl,
                ], 422);
            }
            return redirect($profileUrl);
        }

        $result = app(EventRegistrationGuard::class)->check(
            $user,
            $occurrence,
            ['position' => $request->input('position')]
        );

        if (!$result->allowed) {
            // Незаполненный профиль — редирект на страницу заполнения
            if ($result->meta['profile_required'] ?? false) {
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'ok'       => false,
                        'message'  => 'Для записи на это мероприятие необходимо заполнить личные данные.',
                        'redirect' => route('profile.complete'),
                    ], 422);
                }
                return redirect()->route('profile.complete')
                    ->with('warning', 'Для записи на «' . ($event->title ?? 'мероприятие') . '» необходимо заполнить личные данные.');
            }

            // Уведомляем пользователя об ошибке записи (inApp + мессенджеры)
            if ($user) {
                $eventTitle = (string)($occurrence->event->title ?? ('#' . $occurrence->event_id));
                $reason = implode(' ', $result->errors);
                try {
                    $this->userNotificationService->createRegistrationFailedNotification(
                        userId: (int)$user->id,
                        eventId: (int)$occurrence->event_id,
                        occurrenceId: (int)$occurrence->id,
                        eventTitle: $eventTitle,
                        reason: $reason
                    );
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('registration_failed notification error: ' . $e->getMessage());
                }
            }

            if (request()->ajax() || request()->wantsJson()) {
                return response()->json(['ok' => false, 'message' => implode(' ', $result->errors)], 422);
            }

            return redirect()
                ->route('events.show', [
                    'event' => (int) $event->id,
                    'occurrence' => (int) $occurrence->id,
                ])
                ->with('error', implode(' ', $result->errors));
        }

        // Нельзя записаться в состав если уже в листе ожидания
        $inWaitlist = \App\Models\OccurrenceWaitlist::where('occurrence_id', $occurrence->id)
            ->where('user_id', $user->id)
            ->exists();
        if ($inWaitlist) {
            $msg = 'Вы уже в листе ожидания. Сначала покиньте резерв, чтобы записаться в состав.';
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => $msg], 422);
            }
            return redirect()->route('events.show', ['event' => (int)$event->id, 'occurrence' => (int)$occurrence->id])
                ->with('error', $msg);
        }

        $position       = $request->input('position');
        $subscriptionId = $request->input('subscription_id');
        $couponCode     = $request->input('coupon_code');

        try {
            return $this->persistRegistration($user, $occurrence, $position, $subscriptionId, $couponCode);
        } catch (\Exception $e) {
            $isNoSlots = in_array($e->getMessage(), [
                'Свободных мест на этой позиции больше нет.',
                'Все места для запасных игроков заняты.',
            ]);
            $friendlyMsg = $isNoSlots
                ? 'Ой, вы не успели занять место 🐌, кто-то был быстрее Вас!'
                : $e->getMessage();

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'ok'       => false,
                    'message'  => $friendlyMsg,
                    'no_slots' => $isNoSlots,
                ], 422);
            }

            return redirect()->route('events.show', [
                'event'      => (int) $event->id,
                'occurrence' => (int) $occurrence->id,
            ])->with('error', $friendlyMsg);
        }
    }

    /**
     * NEW: DELETE /occurrences/{occurrence}/leave
     */
    public function destroyOccurrence(Request $request, EventOccurrence $occurrence)
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        $occurrence->load('event');
        $event = $occurrence->event;

        if (!$event) {
            return redirect()->route('events.index')
                ->with('error', 'Событие не найдено.');
        }

        $guard = app(EventCancellationGuard::class);
        $result = $guard->check($user, $occurrence);

        if (!$result->allowed) {
            return redirect()
                ->route('events.show', [
                    'event'      => (int) $event->id,
                    'occurrence' => (int) $occurrence->id,
                ])
                ->with('error', implode(' ', $result->errors));
        }

        return $this->persistCancellation($user, $occurrence);
    }

    private function persistRegistration(
        User $user,
        EventOccurrence $occurrence,
        ?string $position = null,
        ?int $subscriptionId = null,
        ?string $couponCode = null
    ) {
        $created = false;

        DB::transaction(function () use ($user, $occurrence, $position, $subscriptionId, $couponCode, &$created) {
            $roleKey = $position ? (crc32($position) & 0x7fffffff) : 0;

            DB::select(
                'SELECT pg_advisory_xact_lock(?, ?)',
                [$occurrence->id, $roleKey]
            );

            $existing = EventRegistration::query()
                ->where('user_id', $user->id)
                ->where('occurrence_id', $occurrence->id)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if (Schema::hasColumn('event_registrations', 'status')) {
                    $existing->status = 'confirmed';
                }

                if (Schema::hasColumn('event_registrations', 'is_cancelled')) {
                    $existing->is_cancelled = false;
                }

                if (Schema::hasColumn('event_registrations', 'cancelled_at')) {
                    $existing->cancelled_at = null;
                }

                if (Schema::hasColumn('event_registrations', 'position')) {
                    $existing->position = $position ?: null;
                }

                // Сбрасываем payment_status чтобы пересоздать платёж
                $existing->payment_status = null;
                $existing->payment_id = null;
                $existing->payment_expires_at = null;
                $existing->save();
                $created = true;

                // Создаём новый платёж если мероприятие платное
                $event = $occurrence->event;
                if ($event && $event->is_paid && $event->price_minor > 0) {
                    $paymentService = app(\App\Services\PaymentService::class);
                    $payment = $paymentService->createForRegistration($existing, $event, $occurrence);
                    if (in_array($event->payment_method, ['tbank_link', 'sber_link'])) {
                        $existing->payment_status = 'link_pending';
                        $existing->payment_id = $payment->id;
                    } elseif ($event->payment_method === 'yoomoney') {
                        $existing->payment_status = 'pending';
                        $existing->payment_id = $payment->id;
                        $existing->payment_expires_at = $payment->expires_at;
                    }
                    $existing->save();
                }

                return;
            }

            if ($position) {
                if ($position === 'reserve') {
                    $occurrence->event->loadMissing('gameSettings');
                    $reserveMax = (int) ($occurrence->event->gameSettings?->reserve_players_max ?? 0);
                    if ($reserveMax <= 0) {
                        throw new \Exception('Запись запасных игроков не предусмотрена.');
                    }
                    $reserveCount = \DB::table('event_registrations')
                        ->where('occurrence_id', $occurrence->id)
                        ->where('position', 'reserve')
                        ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
                        ->whereRaw("(status IS NULL OR status != 'cancelled')")
                        ->whereNull('cancelled_at')
                        ->count();
                    if ($reserveCount >= $reserveMax) {
                        throw new \Exception('Все места для запасных игроков заняты.');
                    }
                } else {
                    $slotService = app(EventRoleSlotService::class);
                    $ok = $slotService->tryTakeSlot($occurrence->event, $position, $occurrence->id);
                    if (!$ok) {
                        throw new \Exception('Свободных мест на этой позиции больше нет.');
                    }
                }
            }

            $created = true;

            $reg = new EventRegistration();
            $reg->user_id = $user->id;

            if (Schema::hasColumn('event_registrations', 'event_id')) {
                $reg->event_id = $occurrence->event_id;
            }

            if (Schema::hasColumn('event_registrations', 'occurrence_id')) {
                $reg->occurrence_id = $occurrence->id;
            }

            if (Schema::hasColumn('event_registrations', 'status')) {
                $reg->status = 'confirmed';
            }

            if (Schema::hasColumn('event_registrations', 'is_cancelled')) {
                $reg->is_cancelled = false;
            }

            if (Schema::hasColumn('event_registrations', 'position')) {
                $reg->position = $position ?: null;
            }

            $reg->save();

            // Абонемент / купон / оплата
            $event = $occurrence->event;

            // Приоритет 1: Абонемент
            if ($subscriptionId) {
                $subscription = Subscription::where('id', $subscriptionId)
                    ->where('user_id', $user->id)
                    ->where('status', 'active')
                    ->first();

                if ($subscription && $subscription->isUsableForEvent($occurrence->event_id)) {
                    $subService = app(SubscriptionService::class);
                    $usage = $subService->useVisit($subscription, $occurrence, $reg->id);
                    $reg->subscription_id       = $subscription->id;
                    $reg->subscription_usage_id = $usage->id;
                    $reg->payment_status        = 'subscription';
                    $reg->save();
                }
            }
            // Приоритет 2: Купон
            elseif ($couponCode) {
                $coupon = app(CouponService::class)->findByCode($couponCode);
                if ($coupon && $coupon->user_id === $user->id && $coupon->isUsableForEvent($occurrence->event_id)) {
                    $discountPct = app(CouponService::class)->apply($coupon, $occurrence, $reg->id);
                    $reg->coupon_id           = $coupon->id;
                    $reg->coupon_discount_pct = $discountPct;

                    // Применяем скидку к оплате если мероприятие платное
                    if ($event && $event->is_paid && $event->price_minor > 0) {
                        $discountedPrice = (int)round($event->price_minor * (1 - $discountPct / 100));
                        $paymentService = app(PaymentService::class);
                        $payment = $paymentService->createForRegistration($reg, $event, $occurrence, $discountedPrice);
                        $reg->payment_id = $payment->id;
                        $reg->payment_status = in_array($event->payment_method, ['tbank_link', 'sber_link']) ? 'link_pending' : 'pending';
                    }
                    $reg->save();
                }
            }
            // Приоритет 3: Обычная оплата
            elseif ($event && $event->is_paid && $event->price_minor > 0) {
                $paymentService = app(PaymentService::class);
                $payment = $paymentService->createForRegistration($reg, $event, $occurrence);

                if ($event->payment_method === 'yoomoney') {
                    $reg->payment_status     = 'pending';
                    $reg->payment_id         = $payment->id;
                    $reg->payment_expires_at = $payment->expires_at;
                    $reg->save();
                } elseif (in_array($event->payment_method, ['tbank_link', 'sber_link'])) {
                    $reg->payment_status = 'link_pending';
                    $reg->payment_id     = $payment->id;
                    $reg->save();
                } else {
                    $reg->payment_status = 'free';
                    $reg->save();
                }
            }
        });

        app(EventOccurrenceStatsService::class)->increment($occurrence->id);

        // Если есть свободные запасные места и кто-то в листе ожидания — авто-записываем в reserve.
        // Триггер нужен при новой регистрации (а не только при отмене), т.к. reserve-слоты
        // изначально пусты и onSpotFreed для них никогда не срабатывал.
        $isIndividualTournament = (string)($occurrence->event->format ?? '') === 'tournament'
            && (string)($occurrence->event->registration_mode ?? '') === 'tournament_individual';
        if ($created && ((string)($occurrence->event->format ?? '') !== 'tournament' || $isIndividualTournament)) {
            $reserveMax = (int)($occurrence->event->gameSettings?->reserve_players_max ?? 0);
            if ($reserveMax > 0) {
                $reserveTaken = \DB::table('event_registrations')
                    ->where('occurrence_id', $occurrence->id)
                    ->where('position', 'reserve')
                    ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
                    ->whereNull('cancelled_at')
                    ->count();
                $hasWaitlist = \DB::table('occurrence_waitlist')
                    ->where('occurrence_id', $occurrence->id)
                    ->exists();
                if ($reserveTaken < $reserveMax && $hasWaitlist) {
                    app(\App\Services\WaitlistService::class)->autoBookNext($occurrence, 'reserve');
                }
            }
        }

        event(new \App\Events\PlayerJoinedOccurrence(
            $occurrence->id,
            $user->name,
            $position ?? 'player'
        ));

        if ($created) {
            $eventTitle = (string) ($occurrence->event->title ?? ('#' . $occurrence->event_id));

            $this->userNotificationService->createRegistrationCreatedNotification(
                userId: (int) $user->id,
                eventId: (int) $occurrence->event_id,
                occurrenceId: (int) $occurrence->id,
                eventTitle: $eventTitle
            );
        }
        $this->dispatchAnnounceUpdate($occurrence);

        \App\Jobs\NotifyOrganizerRegistrationJob::dispatch(
            (int) $occurrence->id,
            (int) $user->id,
            'registered'
        )->onQueue('default')->afterCommit();

        // Уведомляем друзей пользователя о его записи
        if ($created) {
            try {
                $friends = \App\Models\Friendship::where('friend_id', $user->id)
                    ->pluck('user_id');

                foreach ($friends as $friendUserId) {
                    $this->userNotificationService->createFriendJoinedNotification(
                        userId: (int)$friendUserId,
                        friendId: (int)$user->id,
                        friendName: $user->name,
                        eventId: (int)$occurrence->event_id,
                        occurrenceId: (int)$occurrence->id,
                        eventTitle: $eventTitle ?? ('#' . $occurrence->event_id)
                    );
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('friend_joined notification error: ' . $e->getMessage());
            }

            // Уведомляем премиум-подписчиков (следят за записями этого игрока)
            try {
                app(PlayerFollowService::class)->notifyFollowers($user, $occurrence);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('follow notification error: ' . $e->getMessage());
            }
        }

        // Редирект для платных мероприятий
        $eventModel = $occurrence->event;
        if ($eventModel && $eventModel->is_paid && $eventModel->payment_method === 'yoomoney') {
            $payment = Payment::where('user_id', $user->id)
                ->where('occurrence_id', $occurrence->id)
                ->where('status', 'pending')
                ->latest()
                ->first();

            if ($payment) {
                return redirect()->route('events.show', [
                    'event' => (int) $occurrence->event_id,
                    'occurrence' => (int) $occurrence->id,
                ])->with('payment_pending', $payment->id)
                  ->with('status', '⏳ Место зарезервировано! Оплатите в течение ' . ($eventModel->payment_hold_minutes ?? 15) . ' минут.');
            }
        }

        // Определяем платёжный статус
        $payment = null;
        $paymentStatus = null;
        $paymentLink = null;
        $paymentExpiresAt = null;

        if ($eventModel && $eventModel->is_paid) {
            $payment = Payment::where('user_id', $user->id)
                ->where('occurrence_id', $occurrence->id)
                ->whereIn('status', ['pending', 'paid'])
                ->latest()->first();

            if ($payment) {
                $paymentStatus = $payment->status === 'paid' ? 'paid'
                    : ($payment->method === 'yoomoney' ? 'yoomoney_pending'
                    : (in_array($payment->method, ['tbank_link', 'sber_link']) ? 'link_pending' : null));
                // Берём ссылку из payment_settings организатора
                $paymentLink = null;
                $ps = \Illuminate\Support\Facades\DB::table('payment_settings')
                    ->where('organizer_id', $eventModel->organizer_id)
                    ->first();
                if ($ps) {
                    if ($payment->method === 'tbank_link') {
                        $paymentLink = $ps->tbank_link ?? null;
                    } elseif ($payment->method === 'sber_link') {
                        $paymentLink = $ps->sber_link ?? null;
                    }
                }
                $paymentExpiresAt = $payment->expires_at?->format('H:i') ?? null;
            }
        }

        // AJAX-ответ
        if (request()->ajax() || request()->wantsJson()) {
            return response()->json([
                'ok'               => true,
                'message'          => $eventModel?->is_paid
                    ? (in_array($eventModel->payment_method, ['tbank_link', 'sber_link'])
                        ? '✅ Записались! Переведите оплату и нажмите «Я оплатил».'
                        : ($eventModel->payment_method === 'yoomoney'
                            ? '⏳ Место зарезервировано! Оплатите в течение ' . ($eventModel->payment_hold_minutes ?? 15) . ' минут.'
                            : 'Записались ✅'))
                    : 'Записались ✅',
                'payment_status'   => $paymentStatus,
                'payment_id'       => $payment?->id,
                'payment_link'     => $paymentLink,
                'payment_expires_at' => $paymentExpiresAt,
                'yoomoney_url'     => $payment?->yoomoney_confirmation_url,
                'user_confirmed'   => $payment?->user_confirmed ?? false,
                'amount'           => $payment ? number_format($payment->amount_minor / 100, 2) : null,
            ]);
        }

        if ($eventModel && $eventModel->is_paid && in_array($eventModel->payment_method, ['tbank_link', 'sber_link'])) {
            return redirect()->route('events.show', [
                'event' => (int) $occurrence->event_id,
                'occurrence' => (int) $occurrence->id,
            ])->with('status', '✅ Записались! Переведите оплату и нажмите «Я оплатил».');
        }

        return redirect()->route('events.show', [
            'event' => (int) $occurrence->event_id,
            'occurrence' => (int) $occurrence->id,
        ])->with('status', 'Записались ✅');
    }

    private function persistCancellation(User $user, EventOccurrence $occurrence)
    {
        $event = $occurrence->event;

        $target = function (string $flashKey, string $flashMsg) use ($event, $occurrence) {
            return redirect()->route('events.show', [
                'event' => (int) $event->id,
                'occurrence' => (int) $occurrence->id,
            ])->with($flashKey, $flashMsg);
        };

        DB::beginTransaction();

        try {
            DB::select(
                'SELECT pg_advisory_xact_lock(?)',
                [(int) $occurrence->event_id]
            );

            $reg = EventRegistration::query()
                ->where('user_id', (int) $user->id)
                ->where('occurrence_id', (int) $occurrence->id)
                ->lockForUpdate()
                ->first();

            if (!$reg) {
                DB::commit();
                return $target('status', 'Вы не были записаны.');
            }

            if (Schema::hasColumn('event_registrations', 'group_key') && !empty($reg->group_key)) {
                $this->groupService->leaveGroup(
                    (int) $occurrence->event_id,
                    (int) $user->id
                );

                $reg->refresh();
            }

            if (Schema::hasColumn('event_registrations', 'status')) {
                $reg->status = 'cancelled';
            }

            if (Schema::hasColumn('event_registrations', 'is_cancelled')) {
                $reg->is_cancelled = true;
            }

            if (Schema::hasColumn('event_registrations', 'cancelled_at')) {
                $reg->cancelled_at = now();
            }

            // Сбрасываем платёж при отмене брони
            if (!empty($reg->payment_id)) {
                $existingPayment = \App\Models\Payment::find($reg->payment_id);
                if ($existingPayment) {
                    if ($existingPayment->status === 'paid') {
                        // Оплачено — возврат на виртуальный кошелёк
                        $refundAmount = app(\App\Services\PaymentService::class)
                            ->calculateRefundAmount($existingPayment, $event);
                        if ($refundAmount > 0) {
                            app(\App\Services\PaymentService::class)
                                ->refund($existingPayment, 'registration_cancelled', $refundAmount);
                        } else {
                            $existingPayment->update(['status' => 'cancelled', 'refund_reason' => 'registration_cancelled']);
                        }
                    } else {
                        // Ещё не оплачено — просто отменяем
                        $existingPayment->update(['status' => 'cancelled', 'refund_reason' => 'registration_cancelled']);
                    }
                }
            }
            $reg->payment_status    = null;
            $reg->payment_id        = null;
            $reg->payment_expires_at = null;

            $reg->save();

            $cancelledPosition = $reg->position;

            DB::commit();

            // Синхронизируем taken_slots после отмены
            if ($cancelledPosition && (string)($event->direction ?? '') === 'classic') {
                app(EventRoleSlotService::class)
                    ->resyncTakenSlots($event, $cancelledPosition, $occurrence->id);
            }

            app(EventOccurrenceStatsService::class)->decrement($occurrence->id);

            $eventTitle = (string) ($event->title ?? ('#' . $occurrence->event_id));

            $this->userNotificationService->createRegistrationCancelledNotification(
                userId: (int) $user->id,
                eventId: (int) $occurrence->event_id,
                occurrenceId: (int) $occurrence->id,
                eventTitle: $eventTitle
            );
            $this->dispatchAnnounceUpdate($occurrence);

            \App\Jobs\NotifyOrganizerRegistrationJob::dispatch(
                (int) $occurrence->id,
                (int) $user->id,
                'cancelled'
            )->onQueue('default')->afterCommit();

            return $target('status', 'Запись отменена ✅');
        } catch (\Throwable $e) {
            DB::rollBack();
            return $target('error', 'Ошибка отмены: ' . $e->getMessage());
        }
    }

    private function getOrCreateFirstOccurrenceForEvent(Event $event): ?EventOccurrence
    {
        if (!Schema::hasTable('event_occurrences')) {
            return null;
        }

        $occ = EventOccurrence::query()
            ->where('event_id', (int) $event->id)
            ->orderBy('starts_at', 'asc')
            ->first();

        if ($occ) {
            return $occ;
        }

        if (!$event->starts_at) {
            return null;
        }

        $startUtc = Carbon::parse($event->starts_at, 'UTC');
        $uniq = "event:{$event->id}:{$startUtc->format('YmdHis')}";

        return EventOccurrence::query()->updateOrCreate(
            ['uniq_key' => $uniq],
            [
                'event_id' => (int) $event->id,
                'starts_at' => $startUtc,
                'ends_at' => $event->ends_at ? Carbon::parse($event->ends_at, 'UTC') : null,
                'timezone' => $event->timezone ?: 'UTC',
                'cancel_self_until' => $event->cancel_self_until ?? null,
                'registration_starts_at' => $event->registration_starts_at ?? null,
                'registration_ends_at' => $event->registration_ends_at ?? null,
                'age_policy' => $event->age_policy ?? 'any',
                'is_snow' => (bool) ($event->is_snow ?? false),
            ]
        );
    }
}