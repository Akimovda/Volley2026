<?php

namespace App\Services;

use App\Models\ClubOrganizerTrust;
use App\Models\CourtBooking;
use App\Models\Event;
use App\Models\LocationCourt;
use App\Models\LocationWorkingHour;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class CourtBookingService
{
    private const TTL_MINUTES = 30;

    public function __construct(
        private CourtPricingService $pricingService,
    ) {}

    /**
     * Создание брони. В транзакции блокируем строку корта (сериализует конкурентные
     * попытки забронировать ЭТОТ корт), затем проверяем пересечения и вставляем —
     * двойная бронь невозможна.
     */
    public function create(User $user, LocationCourt $court, Carbon $startsAt, Carbon $endsAt, ?Event $event = null): CourtBooking
    {
        [$direction, $location] = $this->validateBookingWindow($court, $startsAt, $endsAt);

        $trustLevel = ClubOrganizerTrust::where('location_id', $location->id)
            ->where('organizer_id', $user->id)
            ->value('trust_level') ?? ClubOrganizerTrust::LEVEL_PREPAID_ONLY;

        [$status, $paymentMode, $expiresAt] = match ($trustLevel) {
            ClubOrganizerTrust::LEVEL_TRUSTED => [CourtBooking::STATUS_CONFIRMED, CourtBooking::PAYMENT_MODE_TRUSTED, null],
            ClubOrganizerTrust::LEVEL_ALLOW_ON_SITE => [CourtBooking::STATUS_PENDING, CourtBooking::PAYMENT_MODE_ON_SITE, null],
            // prepaid_only: Фаза 3 — оплата ещё не подключена (Фаза 4), но TTL уже действует,
            // как если бы ждали оплату; подтверждает всё равно владелец клуба.
            default => [CourtBooking::STATUS_PENDING, CourtBooking::PAYMENT_MODE_PREPAID, now()->addMinutes(self::TTL_MINUTES)],
        };

        $priceTotal = $this->pricingService->calculate($court, $startsAt, $endsAt);

        return $this->insertBooking($court, $startsAt, $endsAt, [
            'court_id'      => $court->id,
            'user_id'       => $user->id,
            'event_id'      => $event?->id,
            'occurrence_id' => null,
            'starts_at'     => $startsAt,
            'ends_at'       => $endsAt,
            'status'        => $status,
            'price_total'   => $priceTotal,
            'payment_mode'  => $paymentMode,
            'expires_at'    => $expiresAt,
        ], notify: true);
    }

    /**
     * Ручное добавление брони владельцем локации/админом — статус выбирается сразу
     * (confirmed/paid), payment_mode всегда on_site, TTL не применяется. Клиент —
     * либо пользователь платформы (user), либо гость (guestName/guestPhone) — ровно один из двух.
     *
     * @param array{title?: ?string, color?: ?string, parent_booking_id?: ?int} $extra
     */
    public function createManual(
        LocationCourt $court,
        Carbon $startsAt,
        Carbon $endsAt,
        ?User $user,
        ?string $guestName,
        ?string $guestPhone,
        string $status,
        array $extra = [],
    ): CourtBooking {
        if (!$user && empty($guestName)) {
            throw new InvalidArgumentException('Укажите пользователя платформы или имя гостя.');
        }
        if ($user && !empty($guestName)) {
            throw new InvalidArgumentException('Нельзя одновременно указать пользователя и гостя.');
        }
        if (!in_array($status, [CourtBooking::STATUS_CONFIRMED, CourtBooking::STATUS_PAID], true)) {
            throw new InvalidArgumentException('Недопустимый статус брони.');
        }

        $this->validateBookingWindow($court, $startsAt, $endsAt);

        $priceTotal = $this->pricingService->calculate($court, $startsAt, $endsAt);

        return $this->insertBooking($court, $startsAt, $endsAt, [
            'court_id'           => $court->id,
            'user_id'            => $user?->id,
            'guest_name'         => $user ? null : $guestName,
            'guest_phone'        => $user ? null : $guestPhone,
            'title'              => $extra['title'] ?? null,
            'color'              => $extra['color'] ?? null,
            'parent_booking_id'  => $extra['parent_booking_id'] ?? null,
            'event_id'           => null,
            'occurrence_id'      => null,
            'starts_at'          => $startsAt,
            'ends_at'            => $endsAt,
            'status'             => $status,
            'price_total'        => $priceTotal,
            'payment_mode'       => CourtBooking::PAYMENT_MODE_ON_SITE,
            'expires_at'         => null,
            'cancelled_by'       => null,
        ], notify: false);
    }

    /**
     * Бронирование НЕСКОЛЬКИХ кортов на одно и то же время (напр. под турнир).
     * Без повторения: всё в одной транзакции — конфликт любого корта откатывает
     * ВСЕ вставки (createManual() внутри использует SAVEPOINT через вложенный
     * DB::transaction(), поэтому откат внешней транзакции откатывает и его).
     * С повторением: для каждого корта — своя независимая серия (createManualSeries),
     * конфликтующие даты внутри серии пропускаются как обычно, не аборт всего.
     *
     * @param LocationCourt[] $courts
     * @param array{title?: ?string, color?: ?string} $extra
     * @return array{created: CourtBooking[], skipped: string[]}
     */
    public function createManualMultiCourt(
        array $courts,
        Carbon $startsAt,
        Carbon $endsAt,
        ?User $user,
        ?string $guestName,
        ?string $guestPhone,
        string $status,
        array $extra,
        string $repeat,
        ?Carbon $repeatUntil,
    ): array {
        if ($repeat === CourtBooking::REPEAT_NONE) {
            return DB::transaction(function () use ($courts, $startsAt, $endsAt, $user, $guestName, $guestPhone, $status, $extra) {
                $created = [];
                foreach ($courts as $court) {
                    try {
                        $created[] = $this->createManual($court, $startsAt, $endsAt, $user, $guestName, $guestPhone, $status, $extra);
                    } catch (InvalidArgumentException $e) {
                        throw new InvalidArgumentException("Корт «{$court->name}»: {$e->getMessage()}");
                    }
                }
                return ['created' => $created, 'skipped' => []];
            });
        }

        return DB::transaction(function () use ($courts, $startsAt, $endsAt, $user, $guestName, $guestPhone, $status, $extra, $repeat, $repeatUntil) {
            $allCreated = [];
            $allSkipped = [];
            foreach ($courts as $court) {
                $result = $this->createManualSeries($court, $startsAt, $endsAt, $user, $guestName, $guestPhone, $status, $extra, $repeat, $repeatUntil);
                $allCreated = array_merge($allCreated, $result['created']);
                foreach ($result['skipped'] as $skippedDate) {
                    $allSkipped[] = "{$court->name}: {$skippedDate}";
                }
            }
            return ['created' => $allCreated, 'skipped' => $allSkipped];
        });
    }

    /**
     * Серия повторяющихся ручных броней: первая создаётся как обычно и становится
     * "родителем" (parent_booking_id), остальные привязываются к ней. Дата, занятая
     * пересекающейся бронью, просто пропускается (не прерывает всю серию).
     *
     * @param array{title?: ?string, color?: ?string} $extra
     * @return array{created: CourtBooking[], skipped: string[]}
     */
    public function createManualSeries(
        LocationCourt $court,
        Carbon $firstStartsAt,
        Carbon $firstEndsAt,
        ?User $user,
        ?string $guestName,
        ?string $guestPhone,
        string $status,
        array $extra,
        string $repeat,
        Carbon $repeatUntil,
    ): array {
        $durationMinutes = $firstStartsAt->diffInMinutes($firstEndsAt);

        $starts = [$firstStartsAt->copy()];
        $cursor = $firstStartsAt->copy();
        while (true) {
            $cursor = match ($repeat) {
                CourtBooking::REPEAT_DAILY => $cursor->copy()->addDay(),
                CourtBooking::REPEAT_WEEKLY => $cursor->copy()->addWeek(),
                CourtBooking::REPEAT_BIWEEKLY => $cursor->copy()->addWeeks(2),
                default => null,
            };
            if (!$cursor || $cursor->gt($repeatUntil)) {
                break;
            }
            $starts[] = $cursor->copy();
        }

        $created = [];
        $skipped = [];
        $parentId = null;

        foreach ($starts as $startsAt) {
            $endsAt = $startsAt->copy()->addMinutes($durationMinutes);

            try {
                $booking = $this->createManual(
                    $court, $startsAt, $endsAt, $user, $guestName, $guestPhone, $status,
                    array_merge($extra, ['parent_booking_id' => $parentId])
                );
                $created[] = $booking;
                $parentId ??= $booking->id;
            } catch (InvalidArgumentException $e) {
                $skipped[] = $startsAt->copy()->setTimezone($court->direction->location->effectiveTimezone())->format('d.m.Y H:i');
            }
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * Редактирование ручной/платформенной брони владельцем локации/админом.
     * Пересечения проверяются с исключением самой брони.
     *
     * @param array{court_id?: int, starts_at?: Carbon, ends_at?: Carbon, title?: ?string,
     *              color?: ?string, status?: string, user?: ?User, guest_name?: ?string, guest_phone?: ?string} $data
     * @return array{booking: CourtBooking, schedule_changed: bool}
     */
    public function update(CourtBooking $booking, User $clubOwner, array $data): array
    {
        $this->assertCanManage($booking, $clubOwner);

        $court = isset($data['court_id']) && $data['court_id'] !== $booking->court_id
            ? LocationCourt::with('direction.location')->findOrFail($data['court_id'])
            : ($booking->relationLoaded('court') ? $booking->court : $booking->court()->with('direction.location')->first());

        if ((int) $court->id !== (int) $booking->court_id) {
            $this->assertCanManageCourt($court, $clubOwner);
        }

        $startsAt = $data['starts_at'] ?? $booking->starts_at;
        $endsAt = $data['ends_at'] ?? $booking->ends_at;

        $this->validateBookingWindow($court, $startsAt, $endsAt);

        $scheduleChanged = (int) $court->id !== (int) $booking->court_id
            || !$startsAt->equalTo($booking->starts_at)
            || !$endsAt->equalTo($booking->ends_at);

        $user = array_key_exists('user', $data) ? $data['user'] : $booking->user;
        $guestName = array_key_exists('guest_name', $data) ? $data['guest_name'] : $booking->guest_name;
        $guestPhone = array_key_exists('guest_phone', $data) ? $data['guest_phone'] : $booking->guest_phone;

        if (!$user && empty($guestName)) {
            throw new InvalidArgumentException('Укажите пользователя платформы или имя гостя.');
        }
        if ($user && !empty($guestName)) {
            throw new InvalidArgumentException('Нельзя одновременно указать пользователя и гостя.');
        }

        $status = $data['status'] ?? $booking->status;
        if (!in_array($status, [CourtBooking::STATUS_CONFIRMED, CourtBooking::STATUS_PAID], true)) {
            throw new InvalidArgumentException('Недопустимый статус брони.');
        }

        $priceTotal = $this->pricingService->calculate($court, $startsAt, $endsAt);

        DB::transaction(function () use ($booking, $court, $startsAt, $endsAt, $data, $user, $guestName, $guestPhone, $status, $priceTotal) {
            LocationCourt::where('id', $court->id)->lockForUpdate()->first();

            $overlapping = CourtBooking::where('court_id', $court->id)
                ->where('id', '!=', $booking->id)
                ->active()
                ->where('starts_at', '<', $endsAt)
                ->where('ends_at', '>', $startsAt)
                ->exists();

            if ($overlapping) {
                throw new InvalidArgumentException('Этот корт уже забронирован на выбранное время.');
            }

            $booking->court_id = $court->id;
            $booking->starts_at = $startsAt;
            $booking->ends_at = $endsAt;
            $booking->status = $status;
            $booking->price_total = $priceTotal;
            $booking->user_id = $user?->id;
            $booking->guest_name = $user ? null : $guestName;
            $booking->guest_phone = $user ? null : $guestPhone;
            if (array_key_exists('title', $data)) {
                $booking->title = $data['title'];
            }
            if (array_key_exists('color', $data)) {
                $booking->color = $data['color'];
            }
            $booking->save();
        });

        return ['booking' => $booking->fresh(), 'schedule_changed' => $scheduleChanged];
    }

    /**
     * Отмена брони владельцем локации/админом. scope='this' — только эта бронь;
     * scope='this_and_following' — эта и все последующие брони той же серии
     * (parent_booking_id === корень серии, starts_at >= этой брони).
     *
     * @return CourtBooking[] отменённые брони (для рассылки уведомлений арендаторам)
     */
    public function cancel(CourtBooking $booking, User $clubOwner, ?string $reason, string $scope = 'this'): array
    {
        $this->assertCanManage($booking, $clubOwner);

        if (!in_array($booking->status, CourtBooking::ACTIVE_STATUSES, true)) {
            throw new InvalidArgumentException('Эту бронь нельзя отменить.');
        }

        $toCancel = collect([$booking]);

        if ($scope === 'this_and_following') {
            $rootId = $booking->seriesRootId();
            $toCancel = CourtBooking::where(function ($q) use ($rootId) {
                $q->where('id', $rootId)->orWhere('parent_booking_id', $rootId);
            })
                ->active()
                ->where('starts_at', '>=', $booking->starts_at)
                ->get();
        }

        DB::transaction(function () use ($toCancel, $reason) {
            foreach ($toCancel as $b) {
                $b->status = CourtBooking::STATUS_CANCELLED;
                $b->cancelled_by = 'club';
                $b->cancel_reason = $reason;
                $b->save();
            }
        });

        return $toCancel->all();
    }

    /**
     * Общие проверки времени/длительности + разрешение direction/location корта.
     * Переиспользуется create() и createManual().
     *
     * @return array{0: \App\Models\LocationDirection, 1: \App\Models\Location}
     */
    private function validateBookingWindow(LocationCourt $court, Carbon $startsAt, Carbon $endsAt): array
    {
        if ($endsAt->lte($startsAt)) {
            throw new InvalidArgumentException('Время окончания должно быть позже начала.');
        }
        if ($startsAt->lt(now())) {
            throw new InvalidArgumentException('Нельзя забронировать время в прошлом.');
        }

        $durationMinutes = $startsAt->diffInMinutes($endsAt);
        if ($durationMinutes < 30) {
            throw new InvalidArgumentException('Минимальная длительность брони — 30 минут.');
        }
        if ($durationMinutes % 30 !== 0) {
            throw new InvalidArgumentException('Длительность брони должна быть кратна 30 минутам.');
        }
        if ($startsAt->minute % 30 !== 0) {
            throw new InvalidArgumentException('Время начала должно быть кратно 30 минутам.');
        }

        $direction = $court->relationLoaded('direction') ? $court->direction : $court->direction()->first();
        if (!$direction) {
            throw new InvalidArgumentException('У корта не определено направление.');
        }
        $location = $direction->relationLoaded('location') ? $direction->location : $direction->location()->first();
        if (!$location) {
            throw new InvalidArgumentException('У направления не определена локация.');
        }

        $this->assertWithinWorkingHours($direction, $location, $startsAt, $endsAt);

        return [$direction, $location];
    }

    /**
     * Блокировка строки корта + проверка пересечений + вставка — общая точка для
     * create()/createManual(), гарантирует невозможность двойной брони.
     */
    private function insertBooking(LocationCourt $court, Carbon $startsAt, Carbon $endsAt, array $attributes, bool $notify): CourtBooking
    {
        return DB::transaction(function () use ($court, $startsAt, $endsAt, $attributes, $notify) {
            // Блокируем строку корта — сериализует все конкурентные попытки брони
            // именно этого корта через одну транзакцию за раз.
            LocationCourt::where('id', $court->id)->lockForUpdate()->first();

            $overlapping = CourtBooking::where('court_id', $court->id)
                ->active()
                ->where('starts_at', '<', $endsAt)
                ->where('ends_at', '>', $startsAt)
                ->exists();

            if ($overlapping) {
                throw new InvalidArgumentException('Этот корт уже забронирован на выбранное время.');
            }

            $booking = CourtBooking::create($attributes);

            if ($notify) {
                $this->notifyOwner($booking);
            }

            return $booking;
        });
    }

    /**
     * Доступ владельца локации/админа к корту — используется контроллером ПЕРЕД
     * вызовом createManual(), чтобы отдать 403 до попытки создания брони.
     */
    public function assertCanManageCourt(LocationCourt $court, User $clubOwner): void
    {
        $direction = $court->relationLoaded('direction') ? $court->direction : $court->direction()->first();
        $location = $direction?->relationLoaded('location') ? $direction->location : $direction?->location()->first();

        $isOwner = $location && (int) $location->owner_id === (int) $clubOwner->id;
        $isAdmin = method_exists($clubOwner, 'isAdmin') && $clubOwner->isAdmin();

        if (!$isOwner && !$isAdmin) {
            throw new InvalidArgumentException('Нет прав управлять этим кортом.');
        }
    }

    public function confirm(CourtBooking $booking, User $clubOwner): void
    {
        $this->assertCanManage($booking, $clubOwner);

        if ($booking->status !== CourtBooking::STATUS_PENDING) {
            throw new InvalidArgumentException('Подтвердить можно только бронь в статусе "ожидает".');
        }

        $booking->status = CourtBooking::STATUS_CONFIRMED;
        $booking->expires_at = null;
        $booking->save();
    }

    public function reject(CourtBooking $booking, User $clubOwner, string $reason): void
    {
        $this->assertCanManage($booking, $clubOwner);

        if (!in_array($booking->status, [CourtBooking::STATUS_PENDING, CourtBooking::STATUS_CONFIRMED], true)) {
            throw new InvalidArgumentException('Эту бронь нельзя отклонить.');
        }

        $booking->status = CourtBooking::STATUS_CANCELLED;
        $booking->cancelled_by = 'club';
        $booking->cancel_reason = $reason;
        $booking->save();
    }

    public function cancelByUser(CourtBooking $booking, User $user): void
    {
        if ((int) $booking->user_id !== (int) $user->id) {
            throw new InvalidArgumentException('Это не ваша бронь.');
        }
        if (!in_array($booking->status, CourtBooking::ACTIVE_STATUSES, true)) {
            throw new InvalidArgumentException('Эту бронь нельзя отменить.');
        }

        $booking->status = CourtBooking::STATUS_CANCELLED;
        $booking->cancelled_by = 'user';
        $booking->save();
    }

    private function assertWithinWorkingHours($direction, $location, Carbon $startsAt, Carbon $endsAt): void
    {
        $tz = $location->effectiveTimezone();
        $startLocal = $startsAt->copy()->setTimezone($tz);
        $endLocal = $endsAt->copy()->setTimezone($tz);

        if (!$startLocal->isSameDay($endLocal)) {
            throw new InvalidArgumentException('Бронь не может пересекать полночь.');
        }

        $wh = LocationWorkingHour::where('direction_id', $direction->id)
            ->where('day_of_week', $startLocal->dayOfWeekIso - 1)
            ->first();

        if (!$wh || $wh->is_day_off) {
            throw new InvalidArgumentException('В этот день направление не работает.');
        }

        $opensMin = $this->timeToMin($wh->opens_at);
        $closesMin = $this->timeToMin($wh->closes_at);
        $startMin = $startLocal->hour * 60 + $startLocal->minute;
        $endMin = $endLocal->hour * 60 + $endLocal->minute;

        if ($startMin < $opensMin || $endMin > $closesMin) {
            throw new InvalidArgumentException('Время брони вне режима работы направления.');
        }
    }

    private function assertCanManage(CourtBooking $booking, User $clubOwner): void
    {
        $court = $booking->relationLoaded('court') ? $booking->court : $booking->court()->first();
        $direction = $court?->relationLoaded('direction') ? $court->direction : $court?->direction()->first();
        $location = $direction?->relationLoaded('location') ? $direction->location : $direction?->location()->first();

        $isOwner = $location && (int) $location->owner_id === (int) $clubOwner->id;
        $isAdmin = method_exists($clubOwner, 'isAdmin') && $clubOwner->isAdmin();

        if (!$isOwner && !$isAdmin) {
            throw new InvalidArgumentException('Нет прав управлять этой бронью.');
        }
    }

    /**
     * Уведомление владельцу локации — заглушка (лог), полноценные уведомления
     * (push/telegram/vk/max) появятся в Фазе 6.
     */
    private function notifyOwner(CourtBooking $booking): void
    {
        Log::info('[CourtBooking] Новая бронь ожидает внимания владельца локации', [
            'booking_id' => $booking->id,
            'court_id'   => $booking->court_id,
            'user_id'    => $booking->user_id,
            'status'     => $booking->status,
        ]);
    }

    private function timeToMin(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', substr($time, 0, 5)));
        return $h * 60 + $m;
    }
}
