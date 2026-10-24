<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\TournamentMatch;
use App\Models\TournamentStage;
use App\Services\Broadcast\CourtAutoAssigner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Разовое автоназначение площадок матчам существующих мероприятий (только текущие и будущие).
 * Трогает только матчи без площадки и 'auto'; 'manual', завершённые и начатые — никогда.
 */
class CourtsAutoAssign extends Command
{
    protected $signature = 'courts:auto-assign {--event= : ID мероприятия} {--dry-run : Только сводка, ничего не сохранять}';
    protected $description = 'Автоназначение площадок матчам текущих и будущих мероприятий';

    public function handle(CourtAutoAssigner $assigner): int
    {
        $dry = (bool) $this->option('dry-run');
        $assigner->notify = !$dry;

        $from = now()->subDay();
        $q = Event::query()->whereHas('tournamentStages');
        if ($id = $this->option('event')) {
            $q->whereKey((int) $id);
        } else {
            $q->where(function ($w) use ($from) {
                $w->where('starts_at', '>=', $from)
                    ->orWhereHas('occurrences', fn ($o) => $o->where('starts_at', '>=', $from));
            });
        }

        $rows = [];
        $totalChanged = 0;
        $courtsBefore = 0;

        foreach ($q->orderBy('id')->get() as $event) {
            // Без --event — только стадии текущих/будущих туров (у серии прошедшие туры не трогаем)
            $stages = TournamentStage::where('event_id', $event->id)
                ->when(!$this->option('event'), fn ($sq) => $sq->where(fn ($w) => $w
                    ->whereHas('occurrence', fn ($o) => $o->where('starts_at', '>=', $from))
                    ->orWhere(fn ($n) => $n->whereNull('occurrence_id')->whereHas('event', fn ($e) => $e->where('starts_at', '>=', $from)))))
                ->orderBy('id')->get();
            $before = $assigner->createdCourts;
            $changed = 0;

            // dry-run: всё в транзакции, которую откатываем (площадка «Площадка 1» тоже не сохраняется)
            DB::beginTransaction();
            try {
                foreach ($stages as $stage) {
                    $changed += $assigner->assignStage($stage);
                }
                $left = TournamentMatch::whereIn('stage_id', $stages->pluck('id'))->whereNull('court_id')
                    ->whereNotIn('status', [TournamentMatch::STATUS_COMPLETED, TournamentMatch::STATUS_CANCELLED, TournamentMatch::STATUS_FORFEIT])->count();
                $dry ? DB::rollBack() : DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                $this->error("event {$event->id}: " . $e->getMessage());
                continue;
            }

            $created = $assigner->createdCourts - $before;
            if ($changed === 0 && $created === 0) {
                continue;
            }
            $totalChanged += $changed;
            $courtsBefore += $created;
            $rows[] = [$event->id, mb_substr((string) $event->title, 0, 40), $changed, $created, $left];
        }

        $this->table(['event', 'название', 'матчей назначено', 'создано площадок', 'без площадки осталось'], $rows);
        $this->info(($dry ? '[dry-run] ' : '') . "мероприятий: " . count($rows) . ", матчей: {$totalChanged}, новых площадок: {$courtsBefore}");

        return self::SUCCESS;
    }
}
