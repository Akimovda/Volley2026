<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventCourt;
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

        $board = $stage ? $this->games->leaderboard($stage) : ['rows' => [], 'podium' => ['type' => 'players', 'items' => []]];

        // «Такие же составы, как в матче №N» — предзаполнение формы
        $prefill = ['home' => [], 'away' => []];
        if ($from = (int) $request->query('from', 0)) {
            $src = $matches->firstWhere('id', $from);
            if ($src) {
                $prefill['home'] = $src->teamHome?->members->pluck('user_id')->all() ?? [];
                $prefill['away'] = $src->teamAway?->members->pluck('user_id')->all() ?? [];
            }
        } elseif ($stage && !empty($stage->config['draft'])) {
            // Разделение на команды/пары, выбранное на странице, но ещё не ставшее матчем (автосохраняется) — после перезагрузки на месте
            $prefill['home'] = array_map('intval', (array) ($stage->config['draft']['home'] ?? []));
            $prefill['away'] = array_map('intval', (array) ($stage->config['draft']['away'] ?? []));
        }

        $occurrences = EventOccurrence::where('event_id', $event->id)
            ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
            ->orderByDesc('starts_at')->limit(30)->get();

        $eventTeams = $this->games->eventTeams($event, $occurrence->id);

        return view('games.manage', [
            'checklist'   => $this->checklist($event, $occurrence, $stage, $matches, $eventTeams, $roster),
            'event'       => $event,
            'occurrence'  => $occurrence,
            'occurrences' => $occurrences,
            'stage'       => $stage,
            'config'      => array_merge($this->games->defaultConfig($event), $stage?->config ?? []),   // неполный config не должен давать 25/bo1 в пляжке
            'draft'       => (array) ($stage?->config['draft'] ?? []),
            'roster'      => $roster,
            'eventTeams'  => $eventTeams,
            'positions'   => $this->games->positions($occurrence->id),
            'matches'     => $matches,
            'board'       => $board,
            'prefill'     => $prefill,
            'colors'      => FriendlyGameService::COLORS,
        ]);
    }

    /**
     * Пошаговый чек-лист страницы игры (B6): формат → команды → площадка → матч → ведение счёта → трансляция (необязательно).
     * Статусы считаются по данным; «ready» — обязательные шаги 1–5 выполнены.
     */
    private function checklist(Event $event, $occurrence, ?TournamentStage $stage, $matches, $eventTeams, $roster): array
    {
        $config   = array_merge($this->games->defaultConfig($event), $stage?->config ?? []);
        $draft    = (array) ($stage?->config['draft'] ?? []);
        $complete = $eventTeams->filter(fn ($t) => $t->is_complete)->count();
        $partial  = $eventTeams->count() - $complete;

        $courts = EventCourt::where('event_id', $event->id)->where('occurrence_id', $occurrence->id)
            ->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
        $resolver  = app(\App\Services\Broadcast\BroadcastTargetResolver::class);
        $chanOk    = $courts->filter(fn ($c) => $resolver->resolve($c, $event)['ok'])->count();
        $assigned  = $matches->whereNotNull('court_id');
        $courtName = $assigned->isNotEmpty() ? optional($courts->firstWhere('id', $assigned->first()->court_id))->name : null;
        $current   = $matches->first(fn ($m) => $m->status !== 'completed');

        $steps = [
            ['key' => 'format', 'done' => $stage !== null, 'sentence' => $this->games->formatSentence($config)],
            ['key' => 'teams', 'done' => $complete >= 2 || $matches->isNotEmpty() || (count($draft['home'] ?? []) >= 1 && count($draft['away'] ?? []) >= 1),
                'complete' => $complete, 'partial' => $partial, 'roster' => $roster->count(), 'draft' => [count($draft['home'] ?? []), count($draft['away'] ?? [])]],
            ['key' => 'court', 'done' => $matches->isNotEmpty() && $assigned->count() === $matches->count(), 'name' => $courtName, 'any' => $courts->isNotEmpty()],
            ['key' => 'match', 'done' => $matches->isNotEmpty(), 'count' => $matches->count()],
            ['key' => 'score', 'done' => $matches->contains(fn ($m) => $m->status !== TournamentMatch::STATUS_SCHEDULED), 'match' => $current],
            ['key' => 'stream', 'done' => $courts->isNotEmpty() && $chanOk === $courts->count(), 'optional' => true, 'have' => $chanOk, 'total' => $courts->count()],
        ];

        return [
            'steps' => $steps,
            'ready' => collect($steps)->where('optional', '!=', true)->every(fn ($s) => $s['done']),
        ];
    }

    /**
     * Автосохранение разделения на команды/пары (до создания матча): игроки по сторонам, цвета, выбранные команды мероприятия.
     * Хранится в config служебной стадии (ключ `draft`) и восстанавливается при открытии страницы.
     */
    public function saveDraft(Request $request, Event $event): \Illuminate\Http\JsonResponse
    {
        $this->authorizeManage($request, $event);

        $data = $request->validate([
            'occurrence_id' => ['required', 'integer'],
            'side'          => ['nullable', 'array'],
            'side.*'        => ['nullable', 'in:home,away'],
            'team_side'     => ['nullable', 'array'],
            'team_side.*'   => ['nullable', 'in:home,away'],
            'home_color'    => ['nullable', 'string'],
            'away_color'    => ['nullable', 'string'],
        ]);

        $occurrence = $this->occurrenceOf($event, (int) $data['occurrence_id']);
        $roster     = $this->games->roster($occurrence->id)->pluck('id')->map(fn ($i) => (int) $i)->all();
        $pick = fn (string $s) => collect((array) ($data['side'] ?? []))->filter(fn ($v) => $v === $s)->keys()->map(fn ($k) => (int) $k)->intersect($roster)->values()->all();

        $draft = [
            'home'       => $pick('home'),
            'away'       => $pick('away'),
            'home_color' => isset(FriendlyGameService::COLORS[$data['home_color'] ?? '']) ? $data['home_color'] : null,
            'away_color' => isset(FriendlyGameService::COLORS[$data['away_color'] ?? '']) ? $data['away_color'] : null,
            'team_side'  => collect((array) ($data['team_side'] ?? []))->filter()->mapWithKeys(fn ($v, $k) => [(int) $k => $v])->all(),
        ];

        $stage = $this->games->stageFor($event, $occurrence->id);
        $stage->update(['config' => array_merge($stage->config ?? [], ['draft' => $draft])]);

        return response()->json(['ok' => true, 'home' => count($draft['home']), 'away' => count($draft['away'])]);
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
            'team_side'     => ['nullable', 'array'],
            'team_side.*'   => ['nullable', 'in:home,away'],
            // формат, выбранный на этой же странице: сохраняется вместе с созданием матча («выбрал 21, создал — а сыграли до 25» быть не должно)
            'match_format'        => ['nullable', 'in:bo1,bo3,bo5'],
            'set_points'          => ['nullable', 'integer', 'min:5', 'max:50'],
            'deciding_set_points' => ['nullable', 'integer', 'min:5', 'max:50'],
            'tech_timeout'        => ['nullable', 'boolean'],
        ]);

        $occurrence = $this->occurrenceOf($event, (int) $data['occurrence_id']);

        if (isset($data['match_format'], $data['set_points'], $data['deciding_set_points'])) {
            $this->saveFormat($event, $occurrence->id, $data);
        }

        $homeTeamId = $awayTeamId = null;
        foreach ((array) ($data['team_side'] ?? []) as $tid => $side) {
            if ($side === 'home') {
                $homeTeamId = (int) $tid;
            } elseif ($side === 'away') {
                $awayTeamId = (int) $tid;
            }
        }

        $home = $away = [];
        foreach ((array) ($data['side'] ?? []) as $uid => $side) {
            if ($side === 'home') {
                $home[] = (int) $uid;
            } elseif ($side === 'away') {
                $away[] = (int) $uid;
            }
        }

        try {
            $match = $this->games->createMatch($event, $occurrence->id, $home, $away, $data['home_color'], $data['away_color'], $homeTeamId, $awayTeamId);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('tournament.matches.score.form', $match)
            ->with('success', "Матч №{$match->match_number} создан. Введите счёт (или перейдите на поочковый ввод со статистикой).");
    }

    /** Создать на туре команду из ботов системы (подходящих под требования мероприятия) */
    public function addBotTeam(Request $request, Event $event): RedirectResponse
    {
        $this->authorizeManage($request, $event);

        $data       = $request->validate([
            'occurrence_id' => ['required', 'integer'],
            'team_name'     => ['nullable', 'string', 'max:60'],
        ]);
        $occurrence = $this->occurrenceOf($event, (int) $data['occurrence_id']);

        try {
            $team = $this->games->addBotTeam($event, $occurrence, $request->user(), $data['team_name'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('game.manage', [$event, 'occurrence' => $occurrence->id])
            ->with('success', "Команда «{$team->name}» создана ({$team->members()->count()} ботов) — выберите её стороной 1 или 2 в блоке «Готовые команды».");
    }

    /** Формат матча (партии/очки) — конфиг служебной стадии */
    public function updateConfig(Request $request, Event $event): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $this->authorizeManage($request, $event);

        $data = $request->validate([
            'occurrence_id'       => ['required', 'integer'],
            'match_format'        => ['required', 'in:bo1,bo3,bo5'],
            'set_points'          => ['required', 'integer', 'min:5', 'max:50'],
            'deciding_set_points' => ['required', 'integer', 'min:5', 'max:50'],
            'tech_timeout'        => ['nullable', 'boolean'],
        ]);

        $occurrence = $this->occurrenceOf($event, (int) $data['occurrence_id']);
        $stage      = $this->saveFormat($event, $occurrence->id, $data);

        // Автосохранение со страницы (fetch при смене формата)
        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'text' => $this->games->formatSentence($stage->config ?? [])]);
        }

        return redirect()->route('game.manage', [$event, 'occurrence' => $occurrence->id])
            ->with('success', 'Формат матча сохранён (действует для ещё не завершённых матчей).');
    }

    /** Записывает формат матча в конфиг служебной стадии (создаёт стадию с умолчаниями, если её ещё нет). */
    private function saveFormat(Event $event, int $occurrenceId, array $data): TournamentStage
    {
        $stage = $this->games->stageFor($event, $occurrenceId);
        $stage->update(['config' => array_merge($stage->config ?? [], [
            'match_format'        => $data['match_format'],
            'set_points'          => (int) $data['set_points'],
            'deciding_set_points' => (int) $data['deciding_set_points'],
            'tech_timeout'        => (bool) ($data['tech_timeout'] ?? false),   // B5: технический тайм-аут при 21 (только пляжка)
        ])]);

        return $stage;
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

        $board = $stage ? $this->games->leaderboard($stage) : ['rows' => [], 'podium' => ['type' => 'players', 'items' => []]];

        $occurrences = EventOccurrence::where('event_id', $event->id)
            ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
            ->whereIn('id', TournamentStage::where('event_id', $event->id)->where('type', TournamentStage::TYPE_FRIENDLY)->pluck('occurrence_id'))
            ->orderByDesc('starts_at')->get();

        // Итоги по серии за период (вкладка «Серия»)
        $scope  = $request->query('scope') === 'series' ? 'series' : 'evening';
        $period = in_array($request->query('period'), ['30', '90', '365'], true) ? $request->query('period') : 'all';
        $series = null;
        if ($scope === 'series') {
            $series = $this->games->seriesLeaderboard($event, $period === 'all' ? null : now()->subDays((int) $period));
        }

        // Живое обновление: опрашиваем страницу, пока есть незавершённый матч
        $isLive = $visible->contains(fn ($m) => $m->status !== TournamentMatch::STATUS_COMPLETED);

        return view('games.results', compact(
            'event', 'occurrence', 'occurrences', 'stage', 'visible', 'board', 'scope', 'period', 'series', 'isLive',
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
