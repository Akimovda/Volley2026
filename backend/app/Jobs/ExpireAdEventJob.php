<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExpireAdEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $eventId) {}

    public function handle(): void
    {
        $event = Event::find($this->eventId);

        if (!$event) return;

        // Если уже оплачено — ничего не делаем
        if ($event->ad_payment_status === 'paid') return;

        // Уведомляем организатора
        $organizer = User::find($event->organizer_id);
        if ($organizer) {
            try {
                app(\App\Services\NotificationDeliverySender::class)
                    ->sendToUser($organizer, 
                        "⏰ Рекламное мероприятие «{$event->title}» не было оплачено в течение 2 часов и было удалено.",
                        []
                    );
            } catch (\Throwable $e) {
                Log::warning('ExpireAdEventJob notify failed: ' . $e->getMessage());
            }
        }

        // Помечаем как истёкшее и удаляем
        $event->ad_payment_status = 'expired';
        $event->save();
        $event->delete();

        Log::info("ExpireAdEventJob: event #{$this->eventId} expired and deleted.");
    }
}
