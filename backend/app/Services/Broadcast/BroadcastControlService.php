<?php

namespace App\Services\Broadcast;

use App\Models\BroadcastCourtOverride;
use App\Models\EventCourt;
use InvalidArgumentException;

/** Ручное управление площадкой (пульт): режим и выбор матча. Права проверяет вызывающий. */
class BroadcastControlService
{
    public function __construct(protected CourtStateBroadcaster $broadcaster) {}

    /** $mode null — вернуть автоматику. $matchId null — автовыбор матча. */
    public function setManual(EventCourt $court, ?string $mode, ?int $matchId = null): array
    {
        if ($mode !== null && !in_array($mode, BroadcastCourtOverride::MODES, true)) {
            throw new InvalidArgumentException('Неизвестный режим.');
        }

        BroadcastCourtOverride::updateOrCreate(
            ['court_id' => $court->id],
            ['manual_mode' => $mode, 'manual_mode_at' => $mode ? now() : null, 'manual_match_id' => $matchId]
        );

        return $this->broadcaster->forCourt($court);
    }

    /** Только режим (выбранный вручную матч сохраняется). null — вернуть автоматику. */
    public function setMode(EventCourt $court, ?string $mode): array
    {
        if ($mode !== null && !in_array($mode, BroadcastCourtOverride::MODES, true)) {
            throw new InvalidArgumentException('Неизвестный режим.');
        }
        BroadcastCourtOverride::updateOrCreate(
            ['court_id' => $court->id],
            ['manual_mode' => $mode, 'manual_mode_at' => $mode ? now() : null]
        );

        return $this->broadcaster->forCourt($court);
    }

    /** Только матч (режим сохраняется). null — автовыбор. */
    public function setMatch(EventCourt $court, ?int $matchId): array
    {
        BroadcastCourtOverride::updateOrCreate(['court_id' => $court->id], ['manual_match_id' => $matchId]);

        return $this->broadcaster->forCourt($court);
    }
}
