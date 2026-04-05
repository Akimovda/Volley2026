<?php

namespace App\Jobs;

use App\Models\EventOccurrence;
use App\Services\PublishOccurrenceAnnouncementService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RefreshOccurrenceAnnouncementJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 30;

    public function __construct(public int $occurrenceId) {}

    public function handle(PublishOccurrenceAnnouncementService $service): void
    {
        $occurrence = EventOccurrence::query()
            ->with([
                'event.notificationChannels.channel',
                'event.media',
                'event.location.city',
                'event.organizer',
                'event.gameSettings',
            ])
            ->find($this->occurrenceId);

        if (!$occurrence) {
            return;
        }

        if ($occurrence->event->notificationChannels->isEmpty()) {
            return;
        }

        // refreshOnly=true — только обновляем существующий анонс,
        // не отправляем новое сообщение если анонс ещё не был отправлен
        $service->publish($occurrence, refreshOnly: true);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('RefreshOccurrenceAnnouncementJob failed', [
            'occurrence_id' => $this->occurrenceId,
            'error'         => $e->getMessage(),
        ]);
    }
}