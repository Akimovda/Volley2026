<?php

namespace App\Services\Broadcast;

use App\Models\Event;
use App\Models\EventCourt;
use App\Models\TournamentMatch;
use App\Models\TournamentStage;

/**
 * Чек-лист «Как запустить трансляцию» на странице «Настройка стрима»: статусы шагов считаются автоматически.
 * Шаг 1 — есть матч (friendly: матч игры в этом туре; турнир: матч с командами на площадке тура).
 */
class StreamChecklist
{
    public function __construct(protected BroadcastTargetResolver $targets) {}

    public static function hasMatches(Event $event, ?int $occurrenceId): bool
    {
        $base = TournamentMatch::whereNotNull('team_home_id')->whereNotNull('team_away_id')
            ->whereNotIn('status', [TournamentMatch::STATUS_CANCELLED, TournamentMatch::STATUS_FORFEIT]);

        if ($event->format !== 'tournament') {
            return (clone $base)->whereHas('stage', fn ($q) => $q->where('event_id', $event->id)
                ->where('type', TournamentStage::TYPE_FRIENDLY)->when($occurrenceId, fn ($w) => $w->where('occurrence_id', $occurrenceId)))->exists();
        }

        $courtIds = EventCourt::where('event_id', $event->id)
            ->when($occurrenceId, fn ($q) => $q->where(fn ($w) => $w->where('occurrence_id', $occurrenceId)->orWhereNull('occurrence_id')))->pluck('id');

        return $courtIds->isNotEmpty() && (clone $base)->whereIn('court_id', $courtIds)
            ->whereHas('stage', fn ($q) => $q->where('event_id', $event->id))->exists();
    }

    /** Есть матчи, площадки которым поставила система (не организатор). */
    private function hasAutoAssigned(Event $event, ?int $occurrenceId): bool
    {
        return TournamentMatch::where('court_assigned_by', TournamentMatch::COURT_BY_AUTO)
            ->whereHas('stage', fn ($q) => $q->where('event_id', $event->id)->when($occurrenceId, fn ($w) => $w->where('occurrence_id', $occurrenceId)))
            ->exists();
    }

    /**
     * @param \Illuminate\Support\Collection<int,EventCourt> $courts  площадки тура
     * @param \Illuminate\Support\Collection $activeLinks             активные ссылки оверлея по court_id
     */
    public function build(Event $event, ?int $occurrenceId, $courts, $activeLinks, int $unassigned = 0): array
    {
        $active = $courts->where('is_active', true)->values();
        $total = $active->count();

        $linkOk = $active->filter(fn ($c) => isset($activeLinks[$c->id]))->count();
        $chanOk = $active->filter(fn ($c) => $this->targets->resolve($c, $event)['ok'])->count();
        $hasMatches = self::hasMatches($event, $occurrenceId);

        $steps = [
            'matches' => ['done' => $hasMatches, 'unassigned' => $unassigned, 'auto' => $hasMatches && $this->hasAutoAssigned($event, $occurrenceId)],
            'link'    => ['done' => $total > 0 && $linkOk === $total, 'have' => $linkOk, 'total' => $total],
            'channel' => ['done' => $total > 0 && $chanOk === $total, 'have' => $chanOk, 'total' => $total],
        ];

        return $steps + ['ready' => $steps['matches']['done'] && $steps['link']['done'] && $steps['channel']['done']];
    }
}
