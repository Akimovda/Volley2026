<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventTeam;
use App\Models\TournamentStage;
use App\Services\EventAccessService;
use App\Services\TournamentKingService;
use App\Services\TournamentSetupService;
use App\Services\TournamentTeamService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * King of the Court — официальные правила (probeach.ru), см.
 * app/Services/TournamentKingService.php для механики розыгрышей/раундов.
 * Вынесено из TournamentController.php (тот уже >3800 строк) — та же роль,
 * что у TournamentTeamController рядом с основным контроллером.
 */
class TournamentKingOfCourtController extends Controller
{
    public function __construct(
        private TournamentKingService $kingService,
        private TournamentTeamService $teamService,
        private TournamentSetupService $setupService,
    ) {
    }

    private function authorizeOrganizer(Request $request, Event $event): void
    {
        $user = $request->user();
        if (!$user) abort(403);

        if (!app(EventAccessService::class)->canManageEvent($user, (int) $event->organizer_id)) {
            abort(403, 'Нет прав на управление турниром.');
        }
    }

    private function redirectToSetup(Event $event, ?string $message = null, bool $isError = false, ?string $anchor = null): RedirectResponse
    {
        $occId = request()->input('occurrence_id') ?: request()->query('occurrence_id') ?: null;
        if (!$occId) {
            $referer = request()->header('referer', '');
            if (preg_match('/occurrence_id=(\d+)/', $referer, $m)) {
                $occId = $m[1];
            }
        }

        $url = route('tournament.setup', $event);
        if ($occId) {
            $url .= '?occurrence_id=' . $occId;
        }
        if ($anchor) {
            $url .= '#' . $anchor;
        }

        $redirect = redirect()->to($url);
        if ($message) {
            $redirect = $redirect->with($isError ? 'error' : 'success', $message);
        }

        return $redirect;
    }

    /**
     * Команды события, ещё не назначенные ни на один king_of_court корт этого
     * события (у каждой стадии свой независимый корт/набор из 3-5 команд).
     */
    private function unassignedTeams(Event $event, TournamentStage $stage): \Illuminate\Support\Collection
    {
        $assignedIds = TournamentStage::where('event_id', $event->id)
            ->where('type', TournamentStage::TYPE_KING_OF_COURT)
            ->get()
            ->flatMap(fn($s) => (array) $s->cfg('court_team_ids', []))
            ->unique()
            ->values();

        return EventTeam::where('event_id', $event->id)
            ->when($stage->occurrence_id, fn($q) => $q->where('occurrence_id', $stage->occurrence_id))
            ->whereIn('status', ['submitted', 'approved', 'ready'])
            ->whereNotIn('id', $assignedIds)
            ->get();
    }

    public function assignForm(Request $request, TournamentStage $stage)
    {
        $event = $stage->event;
        $this->authorizeOrganizer($request, $event);

        $teams = $this->unassignedTeams($event, $stage);

        return view('tournaments.king_of_court_assign', [
            'event' => $event,
            'stage' => $stage,
            'teams' => $teams,
            'minTeams' => TournamentKingService::MIN_TEAMS,
            'maxTeams' => TournamentKingService::MAX_TEAMS,
        ]);
    }

    public function assign(Request $request, TournamentStage $stage)
    {
        $event = $stage->event;
        $this->authorizeOrganizer($request, $event);

        $validated = $request->validate([
            'team_ids'          => 'required|array',
            'team_ids.*'        => 'integer|distinct|exists:event_teams,id',
            'draw_mode'         => 'nullable|in:random,seeded',
            'round_duration_min'  => 'nullable|integer|min:1|max:60',
            'final_target_points' => 'nullable|integer|min:1|max:99',
            'force_incomplete'  => 'nullable|boolean',
        ]);

        $count = count($validated['team_ids']);
        if ($count < TournamentKingService::MIN_TEAMS || $count > TournamentKingService::MAX_TEAMS) {
            return $this->redirectToSetup(
                $event,
                'King of the Court: нужно от ' . TournamentKingService::MIN_TEAMS . ' до ' . TournamentKingService::MAX_TEAMS . ' команд на корте (выбрано ' . $count . ').',
                true,
                "stage_{$stage->id}"
            );
        }

        $teams = EventTeam::whereIn('id', $validated['team_ids'])->get();

        $incompleteTeams = $teams->reject(fn($t) => $this->teamService->isRosterComplete($t));
        if ($incompleteTeams->isNotEmpty() && !$request->boolean('force_incomplete')) {
            return $this->redirectToSetup(
                $event,
                'Состав не укомплектован у команд: ' . $incompleteTeams->pluck('name')->implode(', ') . '.',
                true,
                "stage_{$stage->id}"
            );
        }

        $teamIds = $teams->pluck('id')->toArray();
        $drawMode = $validated['draw_mode'] ?? 'random';
        $order = $drawMode === 'seeded'
            ? $this->setupService->sortByRating($teams, $stage)->pluck('id')->toArray()
            : collect($teamIds)->shuffle()->values()->toArray();

        DB::transaction(function () use ($stage, $teamIds, $order, $validated) {
            $stage->update([
                'config' => array_merge($stage->config ?? [], [
                    'round_duration_min'  => (int) ($validated['round_duration_min'] ?? 15),
                    'final_target_points' => (int) ($validated['final_target_points'] ?? 15),
                ]),
            ]);

            $this->kingService->initialize($stage, $teamIds);
            $stage->update(['status' => TournamentStage::STATUS_IN_PROGRESS]);
            $this->kingService->startRound($stage->fresh(), $order);
        });

        return $this->redirectToSetup($event, 'Корт собран, раунд 1 начат.', false, "stage_{$stage->id}");
    }

    public function scoreForm(Request $request, TournamentStage $stage)
    {
        $event = $stage->event;
        $this->authorizeOrganizer($request, $event);

        $state = $this->kingService->currentState($stage);
        if (!$state) {
            return $this->redirectToSetup($event, 'Раунд ещё не начат.', true, "stage_{$stage->id}");
        }

        $teamsById = EventTeam::whereIn('id', array_merge(
            [$state['king_team_id'], $state['challenger_team_id']],
            $state['queue']
        ))->get()->keyBy('id');

        return view('tournaments.score_king_of_court', [
            'event'    => $event,
            'stage'    => $stage,
            'state'    => $state,
            'teamsById' => $teamsById,
        ]);
    }

    public function point(Request $request, TournamentStage $stage)
    {
        $event = $stage->event;
        $this->authorizeOrganizer($request, $event);

        $validated = $request->validate([
            'event_type' => 'required|in:king_point,fault,takeover',
        ]);

        try {
            $this->kingService->recordEvent($stage, $validated['event_type'], $request->user()->id);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('tournament.kingOfCourt.scoreForm', $stage);
    }

    public function undo(Request $request, TournamentStage $stage)
    {
        $event = $stage->event;
        $this->authorizeOrganizer($request, $event);

        $this->kingService->undoLast($stage);

        return redirect()->route('tournament.kingOfCourt.scoreForm', $stage);
    }

    public function endRound(Request $request, TournamentStage $stage)
    {
        $event = $stage->event;
        $this->authorizeOrganizer($request, $event);

        try {
            $this->kingService->endRound($stage);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $fresh = $stage->fresh();
        $message = $fresh->status === TournamentStage::STATUS_COMPLETED
            ? 'King of the Court завершён.'
            : 'Раунд завершён.';

        return $this->redirectToSetup($event, $message, false, "stage_{$stage->id}");
    }

    public function nextRound(Request $request, TournamentStage $stage)
    {
        $event = $stage->event;
        $this->authorizeOrganizer($request, $event);

        if ($stage->cfg('round_status') !== 'finished') {
            return back()->with('error', 'Текущий раунд ещё не завершён.');
        }

        try {
            $this->kingService->startRound($stage);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return $this->redirectToSetup($event, 'Следующий раунд начат.', false, "stage_{$stage->id}");
    }

    public function saveColors(Request $request, TournamentStage $stage)
    {
        $event = $stage->event;
        $this->authorizeOrganizer($request, $event);

        $validated = $request->validate([
            'colors'          => 'required|array',
            'colors.*'        => 'nullable|string|regex:/^#[0-9a-fA-F]{6}$/',
        ]);

        $stage->update([
            'config' => array_merge($stage->config ?? [], [
                'team_colors' => $validated['colors'],
            ]),
        ]);

        return $this->redirectToSetup($event, 'Цвета команд сохранены.', false, "stage_{$stage->id}");
    }
}
