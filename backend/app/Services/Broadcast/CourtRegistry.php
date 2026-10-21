<?php

namespace App\Services\Broadcast;

use App\Models\EventCourt;
use App\Models\TournamentMatch;
use App\Models\TournamentStage;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Площадки трансляции (event_courts) и их связь с матчами (tournament_matches.court_id).
 * Строку tournament_matches.court НЕ трогаем: в ней же лежат служебные метки (Grand Final, «Матч за N-M место»).
 */
class CourtRegistry
{
    /** Служебные метки в колонке court — это не площадки. */
    public static function isServiceLabel(?string $name): bool
    {
        $name = trim((string) $name);
        if ($name === '') {
            return true;
        }
        return in_array($name, ['Grand Final', 'Grand Final Reset', '3rd place'], true)
            || preg_match('/за\s+\d+-\d+\s+место/u', $name) === 1;
    }

    /** Реальный корт: не служебная метка и либо «Корт N…», либо из конфигурации стадии/групп. */
    public static function isRealCourt(?string $name, array $known = []): bool
    {
        if (self::isServiceLabel($name)) {
            return false;
        }
        $name = trim((string) $name);
        return preg_match('/^Корт \d+/u', $name) === 1 || in_array($name, $known, true);
    }

    /** Корты, объявленные в стадии и её группах. */
    public static function knownCourtsOfStage(TournamentStage $stage): array
    {
        $known = array_map('trim', array_filter((array) $stage->cfg('courts', []), 'is_string'));
        foreach ($stage->groups as $g) {
            foreach ((array) ($g->courts ?? []) as $c) {
                if (is_string($c)) {
                    $known[] = trim($c);
                }
            }
        }
        return array_values(array_unique($known));
    }

    public function ensure(int $eventId, ?int $occurrenceId, string $name): EventCourt
    {
        $name = trim($name);
        $find = fn () => EventCourt::where('event_id', $eventId)
            ->where('name', $name)
            ->when($occurrenceId === null, fn ($q) => $q->whereNull('occurrence_id'), fn ($q) => $q->where('occurrence_id', $occurrenceId))
            ->first();

        if ($c = $find()) {
            return $c;
        }

        $sort = preg_match('/(\d+)\s*$/u', $name, $m) ? (int) $m[1] : 100 + (int) EventCourt::where('event_id', $eventId)->count();

        try {
            return DB::transaction(fn () => EventCourt::create([
                'event_id'      => $eventId,
                'occurrence_id' => $occurrenceId,
                'name'          => $name,
                'sort_order'    => $sort,
                'is_active'     => true,
            ]));
        } catch (QueryException $e) {
            if ((string) $e->getCode() === '23505' && ($c = $find())) {
                return $c; // параллельное создание
            }
            throw $e;
        }
    }

    /** Площадка для матча по его строке court; служебная метка/пусто — null (назначается вручную). */
    public function courtForMatchLabel(TournamentStage $stage, ?string $label, array $known = []): ?EventCourt
    {
        if (!self::isRealCourt($label, $known ?: self::knownCourtsOfStage($stage))) {
            return null;
        }
        return $this->ensure((int) $stage->event_id, $stage->occurrence_id ? (int) $stage->occurrence_id : null, (string) $label);
    }

    /** Площадка матча: court_id, иначе «площадка дня» friendly-игры (первая площадка тура). */
    public function courtOfMatch(TournamentMatch $match): ?EventCourt
    {
        if ($match->court_id) {
            return EventCourt::find($match->court_id);
        }
        $stage = $match->stage ?? TournamentStage::find($match->stage_id);
        if (!$stage || $stage->type !== TournamentStage::TYPE_FRIENDLY || !$stage->occurrence_id) {
            return null;
        }
        return EventCourt::where('event_id', $stage->event_id)->where('occurrence_id', $stage->occurrence_id)
            ->orderBy('sort_order')->orderBy('id')->first();
    }

    /**
     * Бэкфилл: площадки из реальных кортов существующих матчей + court_id этим матчам.
     * @return array{courts:int, matches:int, skipped_labels:array<string,int>}
     */
    public function backfill(): array
    {
        $courtsBefore = EventCourt::count();
        $matches = 0;
        $skipped = [];

        TournamentStage::with('groups')
            ->whereHas('matches', fn ($q) => $q->whereNotNull('court')->whereNull('court_id'))
            ->orderBy('id')
            ->chunkById(100, function ($stages) use (&$matches, &$skipped) {
                foreach ($stages as $stage) {
                    $known = self::knownCourtsOfStage($stage);
                    $labels = TournamentMatch::where('stage_id', $stage->id)
                        ->whereNotNull('court')->whereNull('court_id')
                        ->distinct()->pluck('court');
                    foreach ($labels as $label) {
                        $court = $this->courtForMatchLabel($stage, $label, $known);
                        if (!$court) {
                            $skipped[$label] = ($skipped[$label] ?? 0) + 1;
                            continue;
                        }
                        $matches += TournamentMatch::where('stage_id', $stage->id)
                            ->where('court', $label)->whereNull('court_id')
                            ->update(['court_id' => $court->id]);
                    }
                }
            });

        return ['courts' => EventCourt::count() - $courtsBefore, 'matches' => $matches, 'skipped_labels' => $skipped];
    }
}
