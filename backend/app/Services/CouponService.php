<?php
namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponTemplate;
use App\Models\SubscriptionCouponLog;
use App\Models\EventOccurrence;
use Illuminate\Support\Facades\DB;

class CouponService
{
    /**
     * Выдать купон пользователю
     */
    public function issue(
        CouponTemplate $template,
        int $userId,
        string $channel = 'manual',
        ?int $issuedBy = null
    ): Coupon {
        return DB::transaction(function () use ($template, $userId, $channel, $issuedBy) {
            if (!$template->canIssueMore()) {
                throw new \Exception('Лимит выдачи купонов исчерпан');
            }

            $coupon = Coupon::create([
                'user_id'       => $userId,
                'template_id'   => $template->id,
                'organizer_id'  => $template->organizer_id,
                'code'          => Coupon::generateCode(),
                'starts_at'     => $template->valid_from,
                'expires_at'    => $template->valid_until,
                'uses_total'    => $template->uses_per_coupon,
                'uses_used'     => 0,
                'uses_remaining' => $template->uses_per_coupon,
                'status'        => 'active',
                'issued_by'     => $issuedBy,
                'issue_channel' => $channel,
            ]);

            $template->increment('issued_count');

            SubscriptionCouponLog::write('coupon', $coupon->id, 'issued', [
                'template' => $template->name,
                'channel'  => $channel,
                'code'     => $coupon->code,
            ], $issuedBy);

            return $coupon;
        });
    }

    /**
     * Применить купон при записи
     */
    public function apply(Coupon $coupon, EventOccurrence $occurrence, int $registrationId): int
    {
        return DB::transaction(function () use ($coupon, $occurrence, $registrationId) {
            if (!$coupon->isUsableForEvent($occurrence->event_id)) {
                throw new \Exception('Купон недоступен для этого мероприятия');
            }

            $coupon->decrement('uses_remaining');
            $coupon->increment('uses_used');

            if ($coupon->uses_remaining === 0) {
                $coupon->update(['status' => 'used']);
            }

            SubscriptionCouponLog::write('coupon', $coupon->id, 'used', [
                'occurrence_id'   => $occurrence->id,
                'event_id'        => $occurrence->event_id,
                'registration_id' => $registrationId,
                'discount_pct'    => $coupon->getDiscountPct(),
            ]);

            return $coupon->getDiscountPct();
        });
    }

    /**
     * Вернуть купон при отмене записи
     */
    public function returnCoupon(Coupon $coupon, EventOccurrence $occurrence): void
    {
        DB::transaction(function () use ($coupon, $occurrence) {
            $template = $coupon->template;
            $cancelHours = $template->cancel_hours_before ?? 0;
            $hoursToEvent = now()->diffInHours($occurrence->starts_at, false);
            $action = ($cancelHours > 0 && $hoursToEvent < $cancelHours) ? 'burned' : 'returned';

            if ($action === 'returned') {
                $coupon->increment('uses_remaining');
                $coupon->decrement('uses_used');
                if ($coupon->status === 'used') {
                    $coupon->update(['status' => 'active']);
                }
            }

            SubscriptionCouponLog::write('coupon', $coupon->id, $action, [
                'occurrence_id'  => $occurrence->id,
                'hours_to_event' => $hoursToEvent,
            ]);
        });
    }

    /**
     * Передача купона другому пользователю
     */
    public function transfer(Coupon $coupon, int $toUserId): void
    {
        if (!$coupon->template->transfer_enabled) {
            throw new \Exception('Передача не разрешена для этого купона');
        }

        $fromUserId = $coupon->user_id;
        $coupon->update(['user_id' => $toUserId, 'status' => 'active']);

        SubscriptionCouponLog::write('coupon', $coupon->id, 'transferred', [
            'from_user_id' => $fromUserId,
            'to_user_id'   => $toUserId,
        ]);
    }

    /**
     * Деактивировать просроченные купоны
     */
    public function expireOldCoupons(): int
    {
        $expired = Coupon::where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now()->toDateString())
            ->get();

        foreach ($expired as $coupon) {
            $coupon->update(['status' => 'expired']);
            SubscriptionCouponLog::write('coupon', $coupon->id, 'expired', null, null);
        }

        return $expired->count();
    }

    /**
     * Найти активный купон пользователя для мероприятия
     */
    public function findActiveForEvent(int $userId, int $eventId): ?Coupon
    {
        return Coupon::with('template')
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->where('uses_remaining', '>', 0)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()->toDateString());
            })
            ->get()
            ->first(fn($c) => $c->template->appliesToEvent($eventId));
    }

    /**
     * Получить купон по коду
     */
    public function findByCode(string $code): ?Coupon
    {
        return Coupon::with('template')->where('code', $code)->first();
    }
}
