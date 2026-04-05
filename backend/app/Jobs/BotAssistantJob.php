<?php

namespace App\Jobs;

use App\Models\EventOccurrence;
use App\Services\BotAssistantService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BotAssistantJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public readonly int $occurrenceId
    ) {}

    public function handle(BotAssistantService $service): void
    {
        $occurrence = EventOccurrence::query()
            ->with(['event'])
            ->find($this->occurrenceId);

        if (!$occurrence) {
            Log::warning("BotAssistantJob: occurrence #{$this->occurrenceId} not found");
            return;
        }

        $service->processOccurrence($occurrence);
    }

    public function failed(\Throwable $e): void
    {
        Log::error("BotAssistantJob failed for occurrence #{$this->occurrenceId}: " . $e->getMessage());
    }
}
