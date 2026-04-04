<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class OccurrenceStatsUpdated implements ShouldBroadcast
{
    public int $occurrenceId;
    public int $registeredTotal;

    public function __construct(int $occurrenceId, int $registeredTotal)
    {
        $this->occurrenceId = $occurrenceId;
        $this->registeredTotal = $registeredTotal;
    }

    public function broadcastOn(): Channel
    {
        return new Channel('occurrence.' . $this->occurrenceId);
    }

    public function broadcastAs(): string
    {
        return 'stats.updated';
    }
}
