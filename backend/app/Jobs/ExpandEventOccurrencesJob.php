<?php
namespace App\Jobs;
use App\Models\Event;
use App\Services\OccurrenceExpansionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
final class ExpandEventOccurrencesJob implements ShouldQueue
{
    use Dispatchable, Queueable;
    public function __construct(
        public int $eventId,
        public int $horizonDays = 90,
        public int $maxCreates = 500
    ) {}
    public function handle(OccurrenceExpansionService $svc): void
    {
        $event = Event::with('gameSettings')->find($this->eventId);
        if (!$event) return;
        $svc->expand($event, $this->horizonDays, $this->maxCreates);
    }
}
