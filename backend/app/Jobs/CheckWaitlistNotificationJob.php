<?php

namespace App\Jobs;

use App\Models\OccurrenceWaitlist;
use App\Services\WaitlistService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckWaitlistNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $occurrenceId,
        public readonly string $position, // '' для пляжки
    ) {}

    public function handle(WaitlistService $service): void
    {
        $service->notifyNext($this->occurrenceId, $this->position);
    }
}
