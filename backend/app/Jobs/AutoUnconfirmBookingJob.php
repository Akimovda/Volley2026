<?php
namespace App\Jobs;

use App\Models\EventRegistration;
use App\Models\EventOccurrence;
use App\Services\SubscriptionService;
use App\Services\UserNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoUnconfirmBookingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(
        SubscriptionService $subService,
        UserNotificationService $notificationService
    ): void {
        // Находим мероприятия которые начинаются через 12 часов
        $occurrences = EventOccurrence::whereBetween('starts_at', [
            now()->addHours(11)->addMinutes(55),
            now()->addHours(12)->addMinutes(5),
        ])->get();

        foreach ($occurrences as $occurrence) {
            // Находим неподтверждённые автозаписи
            $regs = EventRegistration::where('occurrence_id', $occurrence->id)
                ->where('is_cancelled', false)
                ->where('payment_status', 'subscription')
                ->whereNotNull('subscription_id')
                ->whereNull('confirmed_at') // поле подтверждения
                ->get();

            foreach ($regs as $reg) {
                DB::transaction(function () use ($reg, $occurrence, $subService, $notificationService) {
                    // Возвращаем посещение
                    if ($reg->subscription_id) {
                        $sub = \App\Models\Subscription::find($reg->subscription_id);
                        if ($sub) {
                            $subService->returnVisit($sub, $occurrence, $reg->id);
                        }
                    }

                    // Отменяем запись
                    $reg->update([
                        'is_cancelled' => true,
                        'cancelled_at' => now(),
                        'status'       => 'cancelled',
                    ]);

                    // Уведомляем
                    $notificationService->create(
                        userId: $reg->user_id,
                        type: 'auto_booking_unconfirmed',
                        title: '❌ Автозапись отменена',
                        body: 'Вы не подтвердили участие за 12 часов. Посещение возвращено в абонемент.',
                        payload: ['occurrence_id' => $occurrence->id],
                        channels: ['in_app', 'telegram', 'vk', 'max'],
                    );

                    Log::info("AutoUnconfirm: reg #{$reg->id} cancelled, visit returned to sub #{$reg->subscription_id}");
                });
            }
        }
    }
}
