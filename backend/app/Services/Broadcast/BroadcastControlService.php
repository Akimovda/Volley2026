<?php

namespace App\Services\Broadcast;

use App\Models\BroadcastCourtOverride;
use App\Models\EventCourt;
use App\Models\TournamentMatch;
use Illuminate\Support\Facades\Log;
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

    /**
     * Игра на матче площадки началась (старт партии / очко): ручная «Заставка» снимается.
     * Остальные ручные режимы (Скрыть, Табло, итоги) не трогаем, manual_match_id сохраняется.
     * Рассылку состояния делает вызывающий сразу после. Сбой не должен ломать ввод счёта.
     */
    public function releaseSplashOnPlay(TournamentMatch $match): void
    {
        try {
            $court = app(CourtRegistry::class)->courtOfMatch($match);
            if ($court) {
                BroadcastCourtOverride::where('court_id', $court->id)->where('manual_mode', 'splash')
                    ->update(['manual_mode' => null, 'manual_mode_at' => null]);
            }
        } catch (\Throwable $e) {
            Log::warning('broadcast.release_splash_failed', ['match_id' => $match->id, 'error' => $e->getMessage()]);
        }
    }
}
