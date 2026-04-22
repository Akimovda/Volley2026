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
        // Occurrence selector
        $occurrences = collect();
        $selectedOccurrence = null;
        if ($event->season_id) {
            $occurrences = $event->occurrences()->orderBy('starts_at')->get();
            $occId = $request->query('occurrence_id');
            if ($occId) {
                $selectedOccurrence = $occurrences->firstWhere('id', $occId);
            }
            if (!$selectedOccurrence && $occurrences->isNotEmpty()) {
                $selectedOccurrence = $occurrences->first();
            }
        }

        $stages = $event->tournamentStages()
            ->when($selectedOccurrence, fn($q) => $q->where('occurrence_id', $selectedOccurrence->id))
            ->with([
                'groups.standings' => fn($q) => $q->with('team.members.user')->orderBy('rank'),
                'matches' => fn($q) => $q->with(['teamHome.members.user', 'teamAway.members.user', 'winner'])
                    ->orderBy('round')->orderBy('match_number'),
            ])
            ->orderBy('sort_order')
            ->get();

        $liveUrl = route('tournament.public.live', $event);

        return view('tournaments.tv', compact('event', 'stages', 'liveUrl', 'occurrences', 'selectedOccurrence'));
    }

    /**
     * PDF — расписание турнира.
     */
    public function pdfSchedule(Request $request, Event $event)
    {
        $stages = $event->tournamentStages()
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
        $stages = $event->tournamentStages()
            ->with([
                'groups.standings' => fn($q) => $q->with('team.members.user')->orderBy('rank'),
                'matches' => fn($q) => $q->with(['teamHome.members.user', 'teamAway.members.user', 'winner'])
                    ->where('status', 'completed')
                    ->orderBy('round')->orderBy('match_number'),
            ])
            ->orderBy('sort_order')
            ->get();

        $topPlayers = app(\App\Services\TournamentStatsService::class)
            ->getTopPlayers($event->id, 20);

        $pdf = Pdf::loadView('tournaments.pdf.results', compact('event', 'stages', 'topPlayers'))
            ->setPaper('a4', 'portrait');

        $filename = 'results_' . $event->id . '_' . now()->format('Ymd') . '.pdf';
        return $pdf->download($filename);
    }
}
