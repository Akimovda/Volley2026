<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

/**
 * Уведомляет публичную страницу турнира, что в match_rally_events что-то
 * изменилось (новое очко или отмена) — только сигнал "перечитай ленту",
 * без самих данных: клиент по этому событию делает fetch() свежего
 * рендера партиала tournaments/_partials/match_progress_fragment.blade.php
 * (тот же Blade-код, что и при обычной загрузке страницы — исключает
 * дублирование/расхождение вёрстки между live-обновлением и полной загрузкой).
 */
class TournamentMatchRallyUpdated implements ShouldBroadcast
{
    use InteractsWithSockets, SerializesModels;

    public string $broadcastQueue = 'broadcasts';

    public function __construct(
        public int $matchId,
        public int $setNumber,
    ) {
    }

    public function broadcastOn(): Channel
    {
        return new Channel("tournament-match.{$this->matchId}");
    }

    public function broadcastAs(): string
    {
        return 'rally.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'match_id'   => $this->matchId,
            'set_number' => $this->setNumber,
        ];
    }
}
