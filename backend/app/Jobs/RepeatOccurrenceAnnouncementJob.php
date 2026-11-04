<?php

namespace App\Jobs;

use App\Models\EventOccurrence;
use App\Services\PublishOccurrenceAnnouncementService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Повтор анонса (кемпы/мастер-классы/турниры) в один канал: новое сообщение, см. PublishOccurrenceAnnouncementService. */
class RepeatOccurrenceAnnouncementJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1; // повтор не критичен: при сбое следующая проверка запустит его снова

    public function __construct(public int $occurrenceId, public int $channelId)
    {
        $this->onQueue(config('queue.telegram_queue', 'default'));
    }

    public function handle(PublishOccurrenceAnnouncementService $service): void
    {
        $occurrence = EventOccurrence::query()
            ->with(['event.notificationChannels.channel', 'event.media', 'event.location'])
            ->find($this->occurrenceId);

        if (!$occurrence || !empty($occurrence->is_cancelled) || !empty($occurrence->cancelled_at)) {
            return;
        }

        $service->publish($occurrence, false, $this->channelId);
    }
}
