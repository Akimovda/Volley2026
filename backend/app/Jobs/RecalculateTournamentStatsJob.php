<?php

namespace App\Jobs;

use App\Models\Event;
use App\Services\TournamentStatsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Обёртка над TournamentStatsService::recalculateTournament() — полный пересчёт
 * player_tournament_stats/career-статистики события + standings всех групп +
 * (для сезонных турниров) TournamentSeasonStatsService::rebuildForSeason() по
 * ВСЕМ событиям сезона. На лиге с большим числом сыгранных туров это ~4 сек —
 * раньше выполнялось синхронно на горячем пути ввода счёта (report_league_tournament_setup_diag_2026-08-07.md).
 *
 * Standings ЗАТРОНУТОЙ ЭТИМ КОНКРЕТНЫМ матчем группы организатор видит без
 * задержки — TournamentMatchService::submitScore()/resetScore() уже пересчитывают
 * её синхронно сразу после сохранения счёта (recalculateGroup() для одной
 * группы, дёшево). Этот job — только "тяжёлый хвост" (весь эвент/сезон),
 * догоняющий за пару секунд; никакого "устаревшего экрана" организатор не видит,
 * т.к. видимая ему таблица уже верна к моменту ответа страницы.
 *
 * ShouldBeUniqueUntilProcessing по event_id — если организатор быстро вводит
 * несколько счетов подряд (несколько матчей за раз), не плодим параллельные
 * тяжёлые пересчёты одного события. ВАЖНО: не обычный ShouldBeUnique — тот
 * держит лок ВСЁ ВРЕМЯ ВЫПОЛНЕНИЯ handle() (секунды), и если во время работы
 * джоба прилетает ещё одно изменение того же события (например, организатор
 * исправил счёт и тут же удалил стадию), второй dispatch молча схлопывается
 * и это изменение может не попасть ни в текущий, ни в какой-либо последующий
 * пересчёт — итоговые данные тихо расходятся с реальностью до следующего
 * независимого триггера для этого же event_id. ShouldBeUniqueUntilProcessing
 * снимает лок сразу как джоб ВЗЯТ воркером из очереди (а не когда закончил) —
 * окно гонки сжимается с "всё время выполнения" до миллисекунд между dequeue
 * и стартом handle(), второй dispatch в реальности встаёт в очередь и досчитает
 * актуальное состояние отдельным запуском вместо того, чтобы потеряться.
 */
class RecalculateTournamentStatsJob implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 15;

    public function __construct(public int $eventId)
    {
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return (string) $this->eventId;
    }

    public function uniqueFor(): int
    {
        return 300;
    }

    public function handle(TournamentStatsService $service): void
    {
        $event = Event::find($this->eventId);
        if (!$event) {
            return;
        }

        Log::info('[TournamentStats] Recalculate started', ['event_id' => $this->eventId]);
        $service->recalculateTournament($event);
        Log::info('[TournamentStats] Recalculate finished', ['event_id' => $this->eventId]);
    }
}
