<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\EventRegistration;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserRestriction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Учёт наличных платежей организатором — страница /profile/transactions/{event}.
 * Единственное место, где отметка "оплатил"/"не оплатил" переводится в реальный
 * статус Payment. Дедлайн авто-бана (12ч) считается от момента КАЖДОГО сохранения
 * этой формы, не от момента регистрации — так решил организатор в задаче.
 */
class CashPaymentTrackingService
{
    private const BAN_HOURS = 12;

    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly UserNotificationService $notificationService,
    ) {}

    /**
     * Список активных регистраций occurrence с их cash-платежами (создаёт Payment,
     * если по какой-то причине его ещё нет — например регистрация старее, чем
     * включение «Учёта платежей»).
     */
    public function getTrackingRows(Event $event, EventOccurrence $occurrence): Collection
    {
        $registrations = EventRegistration::query()
            ->where('occurrence_id', $occurrence->id)
            ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
            ->where('status', '!=', 'cancelled')
            ->with('user:id,first_name,last_name,avatar_media_id')
            ->orderBy('created_at')
            ->get();

        return $registrations->map(function (EventRegistration $reg) use ($event, $occurrence) {
            $payment = Payment::where('occurrence_id', $occurrence->id)
                ->where('user_id', $reg->user_id)
                ->where('method', 'cash')
                ->whereIn('status', ['pending', 'paid'])
                ->latest('id')
                ->first();

            if (!$payment) {
                // Организатор, записавшийся на собственное мероприятие, не платит сам себе.
                $isOrganizerSelf = (int) $event->organizer_id > 0 && (int) $reg->user_id === (int) $event->organizer_id;

                $payment = Payment::create([
                    'user_id'         => $reg->user_id,
                    'organizer_id'    => $event->organizer_id,
                    'event_id'        => $event->id,
                    'occurrence_id'   => $occurrence->id,
                    'registration_id' => $reg->id,
                    'method'          => 'cash',
                    'status'          => $isOrganizerSelf ? 'paid' : 'pending',
                    'amount_minor'    => (int) ($event->price_minor ?? 0),
                    'currency'        => $event->price_currency ?? 'RUB',
                    'org_confirmed'    => $isOrganizerSelf,
                    'org_confirmed_at' => $isOrganizerSelf ? now() : null,
                ]);
            }

            return [
                'registration' => $reg,
                'user'         => $reg->user,
                'payment'      => $payment,
            ];
        });
    }

    /**
     * Сохранение отметок организатора. $paidUserIds — user_id тех, кого организатор
     * отметил как оплативших; остальные из списка occurrence считаются неоплатившими.
     *
     * @return array{confirmed:int, reminded:int}
     */
    public function save(Event $event, EventOccurrence $occurrence, array $paidUserIds, User $actor): array
    {
        $paidUserIds = array_map('intval', $paidUserIds);
        $rows = $this->getTrackingRows($event, $occurrence);

        // Метка "организатор хотя бы раз заходил на учёт платежей этого occurrence" —
        // используется ProcessUnattendedCashPayments, чтобы отличить "забыл проверить"
        // (тогда через 24ч после мероприятия считаем всех оплаченными) от "частично
        // отметил" (тогда неотмеченным шлём напоминание вместо молчаливого автооплата).
        if (is_null($occurrence->cash_payment_reviewed_at)) {
            $occurrence->update(['cash_payment_reviewed_at' => now()]);
        }

        $confirmed = 0;
        $reminded = 0;

        foreach ($rows as $row) {
            /** @var Payment $payment */
            $payment = $row['payment'];
            $userId = (int) $row['registration']->user_id;
            $isPaidNow = in_array($userId, $paidUserIds, true);

            DB::transaction(function () use ($payment, $isPaidNow, $event, $userId) {
                if ($isPaidNow) {
                    if (!$payment->isPaid()) {
                        $this->paymentService->markPaid($payment);
                    }
                    $payment->update([
                        'org_confirmed'        => true,
                        'org_confirmed_at'      => now(),
                        'cash_ban_deadline_at' => null,
                        'cash_banned_at'       => null,
                    ]);
                    $this->liftOrganizerBan($userId, (int) $event->organizer_id);
                } else {
                    // Откат из paid в pending — организатор поправил ошибочную отметку.
                    if ($payment->isPaid()) {
                        $payment->update(['status' => 'pending']);
                        EventRegistration::where('id', $payment->registration_id)
                            ->update(['payment_status' => 'pending']);
                    }
                    $payment->update([
                        'cash_ban_deadline_at' => now()->addHours(self::BAN_HOURS),
                        'cash_banned_at'       => null,
                    ]);
                }
            });

            if ($isPaidNow) {
                $confirmed++;
                $this->notificationService->create(
                    userId: $userId,
                    type: 'cash_payment_confirmed',
                    title: '✅ Оплата получена',
                    body: 'Организатор подтвердил получение оплаты за «' . $event->title . '».',
                    payload: ['payment_id' => $payment->id, 'event_id' => $event->id, 'occurrence_id' => $occurrence->id],
                    channels: ['in_app', 'telegram', 'vk', 'max'],
                );
            } else {
                $reminded++;
                $this->notificationService->create(
                    userId: $userId,
                    type: 'cash_payment_reminder',
                    title: '⚠️ Требуется оплата',
                    body: 'Организатор отметил, что вы ещё не оплатили участие в «' . $event->title . '». '
                        . 'Оплатите в течение ' . self::BAN_HOURS . ' часов, иначе доступ к записи на мероприятия этого организатора будет ограничен.',
                    payload: ['payment_id' => $payment->id, 'event_id' => $event->id, 'occurrence_id' => $occurrence->id],
                    channels: ['in_app', 'telegram', 'vk', 'max'],
                );
            }
        }

        return ['confirmed' => $confirmed, 'reminded' => $reminded];
    }

    /**
     * Снять активный организаторский бан пользователя (если есть) — вызывается
     * при отметке "оплатил".
     */
    private function liftOrganizerBan(int $userId, int $organizerId): void
    {
        UserRestriction::where('user_id', $userId)
            ->where('scope', 'organizer')
            ->where('organizer_id', $organizerId)
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->update(['ends_at' => now()]);
    }
}
