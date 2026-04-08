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
use App\Models\Payment;
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

        $result = app(EventRegistrationGuard::class)->check(
            $user,
            $occurrence,
            ['position' => $request->input('position')]
        );

        if (!$result->allowed) {
            return redirect()
                ->route('events.show', [
                    'event' => (int) $event->id,
                    'occurrence' => (int) $occurrence->id,
                ])
                ->with('error', implode(' ', $result->errors));
        }

        $position = $request->input('position');

        return $this->persistRegistration($user, $occurrence, $position);
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
                    'event' => (int) $event->id,
                    'occurrence' => (int) $occurrence->id,
                ])
                ->with('error', implode(' ', $result->errors));
        }

        return $this->persistCancellation($user, $occurrence);
    }

    private function persistRegistration(
        User $user,
        EventOccurrence $occurrence,
        ?string $position = null
    ) {
        $created = false;

        DB::transaction(function () use ($user, $occurrence, $position, &$created) {
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

                $existing->save();
                $created = true;

                return;
            }

            if ($position) {
                $slotService = app(EventRoleSlotService::class);

                $ok = $slotService->tryTakeSlot(
                    $occurrence->event,
                    $position
                );

                if (!$ok) {
                    throw new \Exception('Свободных мест на этой позиции больше нет.');
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

            // Платёжная логика
            $event = $occurrence->event;
            if ($event && $event->is_paid && $event->price_minor > 0) {
                $paymentService = app(PaymentService::class);
                $payment = $paymentService->createForRegistration($reg, $event, $occurrence);

                // Для ЮМани — статус резерв
                if ($event->payment_method === 'yoomoney') {
                    $reg->payment_status = 'pending';
                    $reg->payment_id = $payment->id;
                    $reg->payment_expires_at = $payment->expires_at;
                    $reg->save();
                } elseif (in_array($event->payment_method, ['tbank_link', 'sber_link'])) {
                    $reg->payment_status = 'link_pending';
                    $reg->payment_id = $payment->id;
                    $reg->save();
                } else {
                    // cash — сразу подтверждаем
                    $reg->payment_status = 'free';
                    $reg->save();
                }
            }
        });

        app(EventOccurrenceStatsService::class)->increment($occurrence->id);

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

            $reg->save();

            DB::commit();

            app(EventOccurrenceStatsService::class)->decrement($occurrence->id);

            $eventTitle = (string) ($event->title ?? ('#' . $occurrence->event_id));

            $this->userNotificationService->createRegistrationCancelledNotification(
                userId: (int) $user->id,
                eventId: (int) $occurrence->event_id,
                occurrenceId: (int) $occurrence->id,
                eventTitle: $eventTitle
            );
            $this->dispatchAnnounceUpdate($occurrence);
            
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