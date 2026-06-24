<?php

namespace App\Jobs;

use App\Services\UserNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BroadcastToRegistrantsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;
    public string $queue = 'broadcasts';

    public function __construct(
        public readonly array  $userIds,
        public readonly array  $channels,
        public readonly string $title,
        public readonly string $body,
        public readonly int    $eventId,
        public readonly int    $occurrenceId,
    ) {}

    public function handle(UserNotificationService $notif): void
    {
        foreach (array_chunk($this->userIds, 100) as $chunk) {
            foreach ($chunk as $userId) {
                try {
                    $notif->create(
                        userId:   (int) $userId,
                        type:     'organizer_broadcast',
                        title:    $this->title,
                        body:     $this->body,
                        payload:  [
                            'event_id'      => $this->eventId,
                            'occurrence_id' => $this->occurrenceId,
                            'format'        => 'plain',
                        ],
                        channels: $this->channels,
                    );
                } catch (\Throwable $e) {
                    Log::warning('BroadcastToRegistrantsJob: failed for user', [
                        'user_id'       => $userId,
                        'event_id'      => $this->eventId,
                        'occurrence_id' => $this->occurrenceId,
                        'error'         => $e->getMessage(),
                    ]);
                }
            }
        }
    }
}
