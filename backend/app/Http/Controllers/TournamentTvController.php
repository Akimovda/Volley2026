<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\TournamentStage;
use App\Models\TournamentMatch;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class TournamentTvController extends Controller
{
    /**
     * TV Mode — полноэкранный зрительский режим.
     */
    public function tv(Request $request, Event $event)
    {
        [$occurrences, $selectedOccurrence, $stages] = $this->resolveTvContext($request, $event, true);

        $liveUrl = route('tournament.public.live', array_filter([
            'event' => $event->id,
            'occurrence_id' => $selectedOccurrence?->id,
        ]));
        $scoreUrl = route('tournament.tv.live', array_filter([
            'event' => $event->id,
            'occurrence_id' => $selectedOccurrence?->id,
        ]));
        $liveCards = $this->liveCards($stages);
        $tvSig = $this->stateSignature($stages);

        return view('tournaments.tv', compact('event', 'stages', 'liveUrl', 'scoreUrl', 'liveCards', 'tvSig', 'occurrences', 'selectedOccurrence'));
    }

    /**
     * JSON для ТВ-режима: текущий счёт идущих матчей (обновляется без перезагрузки страницы, цифры табло переворачиваются)
     * и сигнатура состояния турнира — при её изменении (старт/завершение матча, смена этапа) страница перезагружается.
     */
    public function tvLive(Request $request, Event $event)
    {
        [, , $stages] = $this->resolveTvContext($request, $event, false);

        return response()->json([
            'sig'     => $this->stateSignature($stages),
            'matches' => $this->liveCards($stages),
        ])->header('Cache-Control', 'no-store');
    }

    /** Туры события, выбранный тур и этапы с матчами (общий разбор для tv() и tvLive()). */
    private function resolveTvContext(Request $request, Event $event, bool $withTeams): array
    {
        // Occurrence selector — тот же подход, что в TournamentPublicController::
        // show() (29018cdd): для сезонных турниров $occurrences = все туры серии,
        // для несезонных — только occurrences, где реально есть tournament_stages.
        $occurrences = collect();
        $selectedOccurrence = null;
        if ($event->season_id) {
            $occurrences = $event->occurrences()->orderBy('starts_at')->get();
        } else {
            $stageOccurrenceIds = $event->tournamentStages()
                ->whereNotNull('occurrence_id')
                ->pluck('occurrence_id')
                ->unique();
            if ($stageOccurrenceIds->isNotEmpty()) {
                $occurrences = $event->occurrences()
                    ->whereIn('id', $stageOccurrenceIds->all())
                    ->orderBy('starts_at')
                    ->get();
            }
        }

        $occId = $request->query('occurrence_id');
        if ($occId) {
            $selectedOccurrence = $occurrences->firstWhere('id', $occId);
        }
        if (!$selectedOccurrence && $occurrences->isNotEmpty()) {
            $selectedOccurrence = $event->season_id ? $occurrences->first() : $occurrences->last();
        }

        $stages = $event->tournamentStages()
            ->when($selectedOccurrence, fn($q) => $q->where('occurrence_id', $selectedOccurrence->id))
            ->with($withTeams ? [
                'groups.standings' => fn($q) => $q->with('team.members.user', 'team.captain')->orderBy('rank'),
                'matches' => fn($q) => $q->with(['teamHome.members.user', 'teamHome.captain', 'teamAway.members.user', 'teamAway.captain', 'winner', 'group'])
                    ->orderBy('round')->orderBy('match_number'),
            ] : [
                'matches' => fn($q) => $q->with(['teamHome', 'teamAway', 'group'])->orderBy('round')->orderBy('match_number'),
            ])
            ->orderBy('sort_order')
            ->get();

        return [$occurrences, $selectedOccurrence, $stages];
    }

    /** Идущие матчи: текущий сет (очки), выигранные сеты, площадка, названия. */
    private function liveCards($stages): array
    {
        $rally = app(\App\Services\MatchRallyService::class);
        $cards = [];
        foreach ($stages as $stage) {
            foreach ($stage->matches as $m) {
                if (!$m->isLive()) {
                    continue;
                }
                $state = null;
                try {
                    $state = $rally->computeMatchState($m);
                } catch (\Throwable $e) {
                    $state = null;
                }
                $setNo = $state && !empty($state['sets']) ? (int) array_key_last($state['sets']) : 1;
                $info = $state['sets'][$setNo] ?? null;
                $cards[] = [
                    'id'         => (int) $m->id,
                    'court'      => (string) ($m->court ?? ''),
                    'stage'      => (string) $stage->name,
                    'group'      => (string) ($m->group?->name ?? ''),
                    'home'       => (string) ($m->teamHome?->name ?? 'TBD'),
                    'away'       => (string) ($m->teamAway?->name ?? 'TBD'),
                    'set'        => $setNo,
                    'target'     => (int) ($info['target'] ?? $stage->setPoints()),
                    'pts_home'   => (int) ($info['home'] ?? 0),
                    'pts_away'   => (int) ($info['away'] ?? 0),
                    'sets_home'  => (int) ($state['homeWins'] ?? $m->sets_home ?? 0),
                    'sets_away'  => (int) ($state['awayWins'] ?? $m->sets_away ?? 0),
                ];
            }
        }

        return $cards;
    }

    /** Сигнатура структуры состояния (без живых очков): статусы этапов и матчей, счёт по сетам завершённых. */
    private function stateSignature($stages): string
    {
        $parts = [];
        foreach ($stages as $stage) {
            $parts[] = 's' . $stage->id . ':' . $stage->status;
            foreach ($stage->matches as $m) {
                $parts[] = $m->id . ':' . $m->status . ':' . (int) $m->sets_home . '-' . (int) $m->sets_away . ':' . (int) $m->team_home_id . '-' . (int) $m->team_away_id;
            }
        }

        return md5(implode('|', $parts));
    }

    /**
     * PDF — расписание турнира.
     */
    public function pdfSchedule(Request $request, Event $event)
    {
        // Occurrence-скоуп — тот же подход, что в show()/tv() (29018cdd, 5c0904f4):
        // для сезонных турниров occurrences = все туры серии, для несезонных —
        // только occurrences, где реально есть tournament_stages; дефолт без
        // ?occurrence_id= — последний тур из этого списка.
        $occurrences = collect();
        $selectedOccurrence = null;
        if ($event->season_id) {
            $occurrences = $event->occurrences()->orderBy('starts_at')->get();
        } else {
            $stageOccurrenceIds = $event->tournamentStages()
                ->whereNotNull('occurrence_id')
                ->pluck('occurrence_id')
                ->unique();
            if ($stageOccurrenceIds->isNotEmpty()) {
                $occurrences = $event->occurrences()
                    ->whereIn('id', $stageOccurrenceIds->all())
                    ->orderBy('starts_at')
                    ->get();
            }
        }

        $occId = $request->query('occurrence_id');
        if ($occId) {
            $selectedOccurrence = $occurrences->firstWhere('id', $occId);
        }
        if (!$selectedOccurrence && $occurrences->isNotEmpty()) {
            $selectedOccurrence = $event->season_id ? $occurrences->first() : $occurrences->last();
        }

        $stages = $event->tournamentStages()
            ->when($selectedOccurrence, fn($q) => $q->where('occurrence_id', $selectedOccurrence->id))
            ->with([
                'groups.teams',
                'matches' => fn($q) => $q->with(['teamHome.members.user', 'teamAway.members.user'])
                    ->orderBy('round')->orderBy('match_number'),
            ])
            ->orderBy('sort_order')
            ->get();

        $pdf = Pdf::loadView('tournaments.pdf.schedule', compact('event', 'stages'))
            ->setPaper('a4', 'landscape');

        $filename = 'schedule_' . $event->id . '_' . now()->format('Ymd') . '.pdf';
        return $pdf->download($filename);
    }

    /**
     * PDF — итоговые результаты.
     */
    public function pdfResults(Request $request, Event $event)
    {
        // Occurrence-скоуп — тот же подход, что в show()/tv()/pdfSchedule().
        $occurrences = collect();
        $selectedOccurrence = null;
        if ($event->season_id) {
            $occurrences = $event->occurrences()->orderBy('starts_at')->get();
        } else {
            $stageOccurrenceIds = $event->tournamentStages()
                ->whereNotNull('occurrence_id')
                ->pluck('occurrence_id')
                ->unique();
            if ($stageOccurrenceIds->isNotEmpty()) {
                $occurrences = $event->occurrences()
                    ->whereIn('id', $stageOccurrenceIds->all())
                    ->orderBy('starts_at')
                    ->get();
            }
        }

        $occId = $request->query('occurrence_id');
        if ($occId) {
            $selectedOccurrence = $occurrences->firstWhere('id', $occId);
        }
        if (!$selectedOccurrence && $occurrences->isNotEmpty()) {
            $selectedOccurrence = $event->season_id ? $occurrences->first() : $occurrences->last();
        }

        $stages = $event->tournamentStages()
            ->when($selectedOccurrence, fn($q) => $q->where('occurrence_id', $selectedOccurrence->id))
            ->with([
                'groups.standings' => fn($q) => $q->with('team.members.user')->orderBy('rank'),
                'matches' => fn($q) => $q->with(['teamHome.members.user', 'teamAway.members.user', 'winner'])
                    ->where('status', 'completed')
                    ->orderBy('round')->orderBy('match_number'),
            ])
            ->orderBy('sort_order')
            ->get();

        $ratingData = app(\App\Services\TournamentStatsService::class)
            ->getParticipantRatingTable($event, $selectedOccurrence?->id);

        $pdf = Pdf::loadView('tournaments.pdf.results', compact('event', 'stages', 'ratingData', 'selectedOccurrence'))
            ->setPaper('a4', 'portrait');

        $filename = 'results_' . $event->id . '_' . now()->format('Ymd') . '.pdf';
        return $pdf->download($filename);
    }
}
