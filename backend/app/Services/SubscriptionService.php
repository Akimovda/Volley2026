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
        return DB::transaction(function () use ($template, $userId, $issuedBy, $reason, $paymentStatus) {
            $startsAt = now()->toDateString();
            $expiresAt = null;

            if ($template->valid_until) {
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
    }

    /**
     * Использовать посещение при записи
     */
    public function useVisit(
        Subscription $subscription,
        EventOccurrence $occurrence,
        int $registrationId
    ): SubscriptionUsage {
        return DB::transaction(function () use ($subscription, $occurrence, $registrationId) {
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
}
