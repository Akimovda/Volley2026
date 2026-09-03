<?php

namespace App\Console\Commands;

use App\Models\EventRegistration;
use App\Services\UserNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpirePremiumAutoBookings extends Command
{
    protected $signature = 'premium:expire-auto-bookings';

    protected $description = 'Отменяет premium-автозаписи, не подтверждённые игроком в течение 12 часов';

    public function handle(UserNotificationService $notificationService): int
    {
        $registrations = EventRegistration::with('occurrence.event')
            ->whereNotNull('premium_auto_booking_id')
            ->whereNull('confirmed_at')
            ->whereNotNull('premium_auto_confirm_deadline_at')
            ->where('premium_auto_confirm_deadline_at', '<=', now())
            ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
            ->get();

        $count = 0;

        foreach ($registrations as $reg) {
            try {
                DB::transaction(function () use ($reg) {
                    // Eloquent save() (не Query Builder) — иначе EventRegistrationObserver
                    // не сработает и WaitlistService::onSpotFreed() не вызовется.
                    $reg->status = 'cancelled';
                    $reg->is_cancelled = true;
                    $reg->cancelled_at = now();
                    $reg->save();
                });

                $event = $reg->occurrence?->event;
                $notificationService->create(
                    userId: $reg->user_id,
                    type: 'premium_auto_booking_unconfirmed',
                    title: '❌ Автозапись Premium отменена',
                    body: 'Вы не подтвердили участие в «' . ($event->title ?? 'мероприятии')
                        . '» в течение 12 часов — запись автоматически отменена.',
                    payload: [
                        'event_id'      => $reg->event_id,
                        'occurrence_id' => $reg->occurrence_id,
                    ],
                    channels: ['in_app', 'telegram', 'vk', 'max'],
                );

                $count++;
            } catch (\Throwable $e) {
                Log::error("ExpirePremiumAutoBookings error reg #{$reg->id}: " . $e->getMessage());
            }
        }

        $this->info("ExpirePremiumAutoBookings: cancelled {$count} unconfirmed registration(s).");

        return self::SUCCESS;
    }
}
