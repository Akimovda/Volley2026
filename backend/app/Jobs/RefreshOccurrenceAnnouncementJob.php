<?php

namespace App\Jobs;

use App\Models\EventOccurrence;
use App\Services\PublishOccurrenceAnnouncementService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RefreshOccurrenceAnnouncementJob implements ShouldQueue
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

        $service->publish($occurrence);
    }
}
