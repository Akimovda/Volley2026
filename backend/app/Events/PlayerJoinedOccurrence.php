<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\InteractsWithSockets;

class PlayerJoinedOccurrence implements ShouldBroadcast
{
    use InteractsWithSockets, SerializesModels;

    public int $occurrenceId;
    public string $playerName;
    public string $position;

    /**
     * Очередь для broadcast (не блокирует HTTP запрос)
     */
    public string $broadcastQueue = 'broadcasts';

    public function __construct(
        int $occurrenceId,
        string $playerName,
        string $position
    ) {
        $this->occurrenceId = $occurrenceId;
        $this->playerName = $playerName;
        $this->position = $position;
    }

    /**
     * Канал события
     */
    public function broadcastOn(): Channel
    {
        return new Channel("occurrence.{$this->occurrenceId}");
    }

    /**
     * Имя события для Echo
     */
    public function broadcastAs(): string
    {
        return 'player.joined';
    }

    /**
     * Данные которые отправятся в WebSocket
     */
    public function broadcastWith(): array
    {
        return [
            'occurrence_id' => $this->occurrenceId,
            'player_name'   => $this->playerName,
            'position'      => $this->position,
        ];
    }
}