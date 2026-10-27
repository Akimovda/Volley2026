<?php

namespace App\Jobs;

use App\Services\NotificationDeliverySender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendNotificationDeliveryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $deliveryId
    ) {
        $this->onQueue(config('queue.telegram_queue', 'default'));   // отдельная очередь для мессенджеров (см. TELEGRAM_QUEUE)
    }

    public function handle(NotificationDeliverySender $sender): void
    {
        $sender->sendById($this->deliveryId);
    }
}
