<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

/**
 * Полное состояние площадки для оверлея трансляции. Канал court.{channel_key} публичный,
 * но имя канала — непредсказуемый ключ (event_courts.channel_key), а не id площадки.
 * ShouldBroadcastNow — без очереди, чтобы табло не зависело от задержки воркера.
 */
class CourtStateUpdated implements ShouldBroadcastNow
{
    use InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $channelKey,
        public array $state,
    ) {
    }

    public function broadcastOn(): Channel
    {
        return new Channel("court.{$this->channelKey}");
    }

    public function broadcastAs(): string
    {
        return 'state';
    }

    public function broadcastWith(): array
    {
        return $this->state;
    }
}
