<?php

namespace App\Jobs;

use App\Models\EventOccurrence;
use App\Services\PublishOccurrenceAnnouncementService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PublishOccurrenceRegistrationOpenJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $occurrenceId) {}

    public function handle(PublishOccurrenceAnnouncementService $service): void
    {
        $occurrence = EventOccurrence::query()
            ->with(['event.notificationChannels.channel', 'event.media', 'event.location'])
            ->find($this->occurrenceId);

        if (!$occurrence) {
            return;
        }

        // Сценарий C: job мог встать в очередь ДО отмены (диспетчер-команда
        // events:publish-pending-announcements фильтрует на момент постановки в очередь,
        // но occurrence могли отменить уже ПОСЛЕ dispatch и ДО выполнения) — не публикуем
        // анонс мёртвого события.
        if (!empty($occurrence->is_cancelled) || !empty($occurrence->cancelled_at)) {
            return;
        }

        $service->publish($occurrence);
    }
}
