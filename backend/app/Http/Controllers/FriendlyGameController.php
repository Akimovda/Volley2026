<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\TournamentMatch;
use App\Models\TournamentStage;
use App\Services\EventAccessService;
use App\Services\EventVisibilityService;
use App\Services\FriendlyGameService;
use App\Services\MatchProgressService;
use App\Services\PlayerMatchStatsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * «Игра со статистикой» — обычное мероприятие (format=game) с events.collect_stats:
 * страница организатора (команды из записанных игроков → матчи → счёт/статистика) и публичные результаты.
 * Ввод счёта/статистики — существующие экраны турнирного движка (tournament.matches.*), доступ — владелец/staff.
 */
class FriendlyGameController extends Controller
{
    public function __construct(
        private readonly FriendlyGameService $games,
        private readonly EventAccessService $access,
    ) {}

    /** Страница организатора */
    public function manage(Request $request, Event $event): View
    {
        $this->authorizeManage($request, $event);

        $occurrence = $this->resolveOccurrence($event, (int) $request->query('occurrence', 0));
        $stage      = $this->games->findStage($event, $occurrence->id);
        $roster     = $this->games->roster($occurrence->id);

        $matches = $stage
            ? TournamentMatch::where('stage_id', $stage->id)
                ->with(['teamHome.members.user', 'teamAway.members.user'])
                ->orderBy('match_number')->get()
            : collect();

        $board = $stage ? $this->games->leaderboard($stage) : ['rows' => [], 'podium' => []];

        // «Такие же составы, как в матче №N» — предзаполнение формы
        $prefill = ['home' => [], 'away' => []];
        if ($from = (int) $request->query('from', 0)) {
            $src = $matches->firstWhere('id', $from);
            if ($src) {
                $prefill['home'] = $src->teamHome?->members->pluck('user_id')->all() ?? [];
                $prefill['away'] = $src->teamAway?->members->pluck('user_id')->all() ?? [];
            }
        }

        $occurrences = EventOccurrence::where('event_id', $event->id)
            ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
            ->orderByDesc('starts_at')->limit(30)->get();

        return view('games.manage', [
            'event'       => $event,
            'occurrence'  => $occurrence,
            'occurrences' => $occurrences,
            'stage'       => $stage,
            'config'      => $stage?->config ?? $this->games->defaultConfig(),
            'roster'      => $roster,
            'matches'     => $matches,
            'board'       => $board,
            'prefill'     => $prefill,
            'colors'      => FriendlyGameService::COLORS,
        ]);
    }

    /** Создать матч из двух составов */
    public function storeMatch(Request $request, Event $event): RedirectResponse
    {
        $this->authorizeManage($request, $event);

        $data = $request->validate([
            'occurrence_id' => ['required', 'integer'],
            'side'          => ['nullable', 'array'],
            'side.*'        => ['nullable', 'in:home,away'],
            'home_color'    => ['required', 'string'],
            'away_color'    => ['required', 'string'],
        ]);

        $occurrence = $this->occurrenceOf($event, (int) $data['occurrence_id']);

        $home = $away = [];
        foreach ((array) ($data['side'] ?? []) as $uid => $side) {
            if ($side === 'home') {
                $home[] = (int) $uid;
            } elseif ($side === 'away') {
                $away[] = (int) $uid;
            }
        }

        try {
            $match = $this->games->createMatch($event, $occurrence->id, $home, $away, $data['home_color'], $data['away_color']);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('tournament.matches.rally.form', $match)
            ->with('success', "Матч №{$match->match_number} создан. Вводите счёт и статистику.");
    }

    /** Формат матча (партии/очки) — конфиг служебной стадии */
    public function updateConfig(Request $request, Event $event): RedirectResponse
    {
        $this->authorizeManage($request, $event);

        $data = $request->validate([
            'occurrence_id'       => ['required', 'integer'],
            'match_format'        => ['required', 'in:bo1,bo3,bo5'],
            'set_points'          => ['required', 'integer', 'min:5', 'max:50'],
            'deciding_set_points' => ['required', 'integer', 'min:5', 'max:50'],
        ]);

        $occurrence = $this->occurrenceOf($event, (int) $data['occurrence_id']);
        $stage      = $this->games->stageFor($event, $occurrence->id);

        $stage->update(['config' => array_merge($stage->config ?? [], [
            'match_format'        => $data['match_format'],
            'set_points'          => (int) $data['set_points'],
            'deciding_set_points' => (int) $data['deciding_set_points'],
        ])]);

        return redirect()->route('game.manage', [$event, 'occurrence' => $occurrence->id])
            ->with('success', 'Формат матча сохранён (действует для ещё не завершённых матчей).');
    }

    public function destroyMatch(Request $request, TournamentMatch $match): RedirectResponse
    {
        $stage = $match->stage;
        abort_unless($stage && $stage->type === TournamentStage::TYPE_FRIENDLY, 404);
        $event = $stage->event;
        $this->authorizeManage($request, $event);

        $number = $match->match_number;
        $this->games->deleteMatch($match);

        // Рейтинговая игра: удалённый матч должен пропасть из рейтингов
        if ($event->stats_rated) {
            \App\Jobs\RecalculateTournamentStatsJob::dispatch($event->id)->afterCommit();
        }

        return redirect()->route('game.manage', [$event, 'occurrence' => $stage->occurrence_id])
            ->with('success', "Матч №{$number} удалён.");
    }

    /** Публичная страница результатов и статистики */
    public function results(Request $request, Event $event): View
    {
        abort_unless($event->collect_stats, 404);

        $visibility = app(EventVisibilityService::class);
        if ($visibility->isPrivateEventRow($event)) {
            abort_unless($visibility->canViewPrivateEvent($event, $request->user()), 403);
        }

        $occurrence = $this->resolveOccurrence($event, (int) $request->query('occurrence', 0));
        $stage      = $this->games->findStage($event, $occurrence->id);

        $matches = $stage
            ? TournamentMatch::where('stage_id', $stage->id)
                ->whereNotIn('status', [TournamentMatch::STATUS_CANCELLED])
                ->with(['teamHome.members.user', 'teamAway.members.user', 'stage'])
                ->orderBy('match_number')->get()
            : collect();

        $visible = $matches->filter(fn ($m) => $m->status !== TournamentMatch::STATUS_SCHEDULED || $this->hasRally($m->id))->values();

        $completed = $visible->where('status', TournamentMatch::STATUS_COMPLETED);
        $matchStatsByMatchId    = app(PlayerMatchStatsService::class)->getMatchStatsTableForMatches($completed);
        $matchProgressByMatchId = app(MatchProgressService::class)->buildForMatches($visible);

        $board = $stage ? $this->games->leaderboard($stage) : ['rows' => [], 'podium' => []];

        $occurrences = EventOccurrence::where('event_id', $event->id)
            ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
            ->whereIn('id', TournamentStage::where('event_id', $event->id)->where('type', TournamentStage::TYPE_FRIENDLY)->pluck('occurrence_id'))
            ->orderByDesc('starts_at')->get();

        return view('games.results', compact(
            'event', 'occurrence', 'occurrences', 'stage', 'visible', 'board',
            'matchStatsByMatchId', 'matchProgressByMatchId'
        ));
    }

    private function hasRally(int $matchId): bool
    {
        return \App\Models\MatchRallyEvent::where('match_id', $matchId)->exists();
    }

    private function authorizeManage(Request $request, Event $event): void
    {
        $user = $request->user();
        abort_unless($user, 403);
        abort_unless($this->access->canManageEvent($user, (int) $event->organizer_id), 403, 'Нет прав на управление мероприятием.');
        abort_unless($event->collect_stats, 404);
    }

    private function occurrenceOf(Event $event, int $occurrenceId): EventOccurrence
    {
        return EventOccurrence::where('event_id', $event->id)->where('id', $occurrenceId)->firstOrFail();
    }

    /** ?occurrence=ID, иначе — текущий/ближайший прошедший тур, иначе первый будущий */
    private function resolveOccurrence(Event $event, int $requestedId): EventOccurrence
    {
        if ($requestedId > 0) {
            return $this->occurrenceOf($event, $requestedId);
        }

        $base = EventOccurrence::where('event_id', $event->id)
            ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)');

        return (clone $base)->where('starts_at', '<=', now()->addHours(12))->orderByDesc('starts_at')->first()
            ?? (clone $base)->orderBy('starts_at')->firstOrFail();
    }
}
