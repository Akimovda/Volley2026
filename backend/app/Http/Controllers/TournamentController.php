<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventTeam;
use App\Models\TournamentStage;
use App\Models\EventTeamApplication;
use App\Models\TournamentGroup;
use App\Models\TournamentMatch;
use App\Models\TournamentStanding;
use App\Models\TournamentTiebreaker;
use App\Models\TournamentTiebreakerSet;
use App\Services\TournamentSetupService;
use App\Services\TournamentMatchService;
use App\Services\TournamentStandingsService;
use App\Services\TournamentBracketService;
use App\Services\TournamentKingService;
use App\Services\TournamentKingBeachService;
use App\Services\TournamentSwissService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\PlayerMatchStatsService;
use App\Models\MatchPlayerStats;

class TournamentController extends Controller
{
    public function __construct(
        private TournamentSetupService $setupService,
        private TournamentMatchService $matchService,
        private TournamentStandingsService $standingsService,
        private TournamentBracketService $bracketService,
        private TournamentSwissService $swissService,
        private TournamentKingService $kingService,
        private TournamentKingBeachService $kingBeachService,
    ) {}

    /* ================================================================
     *  Страница настройки турнира (организатор)
     * ================================================================ */

    public function setup(Request $request, Event $event)
    {
        $this->authorizeOrganizer($request, $event);

        $stages = $event->tournamentStages()->with([
            'groups.teams', 'groups.standings',
            'matches' => fn($q) => $q->orderBy('round')->orderBy('match_number'),
            'matches.teamHome', 'matches.teamAway', 'matches.winner',
        ])->get();

        // ── Season / League data + selectedOccurrence (до загрузки teams!) ──
        $seasonData = null;
        $selectedOccurrence = null;
        $leagueTeams = collect();

        if ($event->season_id) {
            $occurrences = $event->occurrences()
                ->whereNull('cancelled_at')
                ->orderBy('starts_at')
                ->get();

            $occId = (int) $request->query('occurrence_id', 0);
            $selectedOccurrence = $occId > 0
                ? $occurrences->firstWhere('id', $occId)
                : $occurrences->first();

            // Находим сезон/лигу по выбранному туру: тур может принадлежать другому сезону
            $seasonEvtForOcc = $selectedOccurrence
                ? \App\Models\TournamentSeasonEvent::where('occurrence_id', $selectedOccurrence->id)->first()
                : null;

            if ($seasonEvtForOcc?->season_id) {
                $season = \App\Models\TournamentSeason::with('leagues.leagueTeams.team.captain', 'leagues.leagueTeams.user', 'seasonEvents')
                    ->find($seasonEvtForOcc->season_id);
            } else {
                $season = $event->season()->with('leagues.leagueTeams.team.captain', 'leagues.leagueTeams.user', 'seasonEvents')->first();
            }

            if ($season) {
                $league = ($seasonEvtForOcc?->league_id)
                    ? ($season->leagues->firstWhere('id', $seasonEvtForOcc->league_id) ?? $season->leagues->first())
                    : $season->leagues->first();

                if ($league) {
                    $leagueTeams = $league->leagueTeams()
                        ->with(['team.captain', 'team.members.user', 'user'])
                        ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'pending_confirmation' THEN 1 WHEN 'reserve' THEN 2 ELSE 3 END")
                        ->orderBy('reserve_position')
                        ->get();
                }

                $seasonData = [
                    'season' => $season,
                    'league' => $league,
                    'occurrences' => $occurrences,
                ];
            }
        }

        // Fallback: если occurrence не выбран через серию — берём ближайший upcoming occurrence события.
        if (!$selectedOccurrence) {
            $selectedOccurrence = $event->occurrences()
                ->whereNull('cancelled_at')
                ->where('starts_at', '>=', now('UTC'))
                ->orderBy('starts_at')
                ->first()
                ?? $event->occurrences()
                    ->whereNull('cancelled_at')
                    ->orderByDesc('starts_at')
                    ->first();
        }

        // Команды фильтруются по текущему туру — чтобы не смешивались команды разных туров
        $teams = EventTeam::where('event_id', $event->id)
            ->when($selectedOccurrence, fn($q) => $q->where('occurrence_id', $selectedOccurrence->id))
            ->whereIn('status', ['draft', 'submitted', 'approved', 'ready'])
            ->with('captain')
            ->get();

        // Все «активные» заявки: ожидающие модерации (pending) + неполные (incomplete).
        // Фильтруем по текущему туру — заявки других туров/сезонов не показываем.
        $pendingApplications = EventTeamApplication::where('event_id', $event->id)
            ->whereIn('status', ['pending', 'incomplete'])
            ->when($selectedOccurrence, fn($q) => $q->whereHas(
                'team', fn($tq) => $tq->where('occurrence_id', $selectedOccurrence->id)
            ))
            ->with(['team.captain', 'team.members.user', 'submittedBy'])
            ->get();

        $settings = $event->tournamentSetting;
        $applicationMode = $settings->application_mode ?? 'manual';

        $userEventPhotos = $request->user()->getMedia('event_photos')->sortByDesc('created_at');

        // Фильтруем стадии по выбранному туру (occurrence)
        if ($selectedOccurrence) {
            $stages = $stages->filter(fn($s) => $s->occurrence_id === null || $s->occurrence_id === $selectedOccurrence->id);
        }

        // Исключаем из общего списка команды, которые в резерве лиги
        if ($leagueTeams->count()) {
            $reserveTeamIds = $leagueTeams
                ->where('status', 'reserve')
                ->pluck('team_id')
                ->filter()
                ->toArray();

            if ($reserveTeamIds) {
                $teams = $teams->reject(fn($t) => in_array($t->id, $reserveTeamIds));
            }
        }

        // Tiebreaker sets (множественные связки команд) — pending + resolved для отображения
        $stageIds = $stages->pluck('id');
        $tiebreakerSets = TournamentTiebreakerSet::whereIn('stage_id', $stageIds)
            ->with('group')
            ->get()
            ->groupBy('group_id');

        // Чистая статистика (без матчей с аутсайдером) и список аутсайдеров — для отображения в таблице
        $cleanStatsByGroup = [];
        $outsidersByGroup  = [];
        foreach ($stages as $st) {
            foreach ($st->groups as $g) {
                $cleanStatsByGroup[$g->id] = $this->standingsService->computeCleanStats($st, $g);
                $outsidersByGroup[$g->id]  = $this->standingsService->getOutsiderTeamIds($g->standings);
            }
        }

        return view('tournaments.setup', compact(
            'event', 'stages', 'teams', 'pendingApplications',
            'applicationMode', 'userEventPhotos',
            'seasonData', 'selectedOccurrence', 'leagueTeams',
            'tiebreakerSets', 'cleanStatsByGroup', 'outsidersByGroup'
        ));
    }

    /* ================================================================
     *  Создать стадию
     * ================================================================ */

    public function createStage(Request $request, Event $event)
    {
        $this->authorizeOrganizer($request, $event);

        // King of the Beach: match_format принудительно bo1
        if ($request->input('type') === TournamentStage::TYPE_KING_BEACH) {
            $request->merge(['match_format' => 'bo1', 'deciding_set_points' => '15']);
        }

        $validated = $request->validate([
            'type'        => 'required|in:' . implode(',', TournamentStage::TYPES),
            'name'        => 'required|string|max:100',
            'match_format' => 'required|in:bo1,bo3,bo5',
            'set_points'  => 'required|integer|in:15,21,25',
            'deciding_set_points' => 'required|integer|in:15,25',
            'groups_count'    => 'nullable|integer|min:1|max:16',
            'advance_count'   => 'nullable|integer|min:1|max:8',
            'third_place_match' => 'nullable|boolean',
            'courts'          => 'nullable|string|max:500',
        ]);

        $sortOrder = ($event->tournamentStages()->max('sort_order') ?? 0) + 1;

        $config = [
            'match_format'       => $validated['match_format'],
            'set_points'         => (int) $validated['set_points'],
            'deciding_set_points' => (int) $validated['deciding_set_points'],
            'groups_count'       => (int) ($validated['groups_count'] ?? 0),
            'advance_count'      => (int) ($validated['advance_count'] ?? 2),
            'third_place_match'  => (bool) ($validated['third_place_match'] ?? false),
            'courts'             => $validated['courts']
                ? array_map('trim', explode(',', $validated['courts']))
                : [],
            'draw_mode'          => $request->input('draw_mode', 'random'),
            'round_number'       => 1,
        ];

        // occurrence_id из hidden field (если сезонный турнир)
        $occurrenceId = $request->input('occurrence_id') ?: null;

        $stage = $this->setupService->createStage($event, [
            'type'          => $validated['type'],
            'name'          => $validated['name'],
            'sort_order'    => $sortOrder,
            'config'        => $config,
            'occurrence_id' => $occurrenceId ? (int) $occurrenceId : null,
        ]);

        // Для Round Robin / Groups+Playoff — автосоздание групп + жеребьёвка
        if (in_array($validated['type'], ['round_robin', 'groups_playoff']) && $config['groups_count'] > 0) {
            $this->setupService->createGroupsAuto($stage, $config['groups_count']);

            // Автоматическая жеребьёвка
            $drawMode = $request->input('draw_mode', 'random');
            $teams = EventTeam::where('event_id', $event->id)
                ->when($occurrenceId, fn($q) => $q->where('occurrence_id', (int) $occurrenceId))
                ->whereIn('status', ['submitted', 'approved', 'ready'])
                ->with('event')
                ->get();

            // Фильтр резерва лиги
            if ($event->season_id) {
                $season = $event->season;
                $league = $season?->leagues()->first();
                if ($league) {
                    $reserveTeamIds = $league->leagueTeams()
                        ->where('status', 'reserve')
                        ->pluck('team_id')->toArray();
                    $teams = $teams->reject(fn($t) => in_array($t->id, $reserveTeamIds));
                }
            }

            if ($teams->count() >= 2) {
                $groups = $stage->groups;

                if ($drawMode === 'manual') {
                    // Ручное распределение: manual_teams[team_id] = 'A'|'B'|...
                    $manualTeams = $request->input('manual_teams', []);
                    $groupLabels = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P'];
                    $groupsByLabel = [];
                    foreach ($groups as $idx => $group) {
                        $label = $groupLabels[$idx] ?? (string)($idx + 1);
                        $groupsByLabel[$label] = $group;
                    }
                    $seedCounters = [];
                    foreach ($manualTeams as $teamId => $label) {
                        if (empty($label) || !isset($groupsByLabel[$label])) continue;
                        $group = $groupsByLabel[$label];
                        if (!isset($seedCounters[$label])) $seedCounters[$label] = 0;
                        $seedCounters[$label]++;
                        \App\Models\TournamentGroupTeam::create([
                            'group_id' => $group->id,
                            'team_id'  => (int) $teamId,
                            'seed'     => $seedCounters[$label],
                        ]);
                    }
                } elseif ($drawMode === 'seeded') {
                    $sorted = $teams->sortByDesc(fn($t) => $this->setupService->getTeamRating($t, $event->id))->values();
                } else {
                    $sorted = $teams->shuffle();
                }

                if ($drawMode !== 'manual') {
                    $groupIdx = 0;
                    $groupCount = $groups->count();
                    foreach ($sorted as $i => $team) {
                        \App\Models\TournamentGroupTeam::create([
                            'group_id' => $groups[$groupIdx % $groupCount]->id,
                            'team_id'  => $team->id,
                            'seed'     => intdiv($i, $groupCount) + 1,
                        ]);
                        $groupIdx++;
                    }
                }

                // Генерация матчей
                foreach ($groups as $group) {
                    $this->setupService->generateRoundRobinMatches($stage, $group);
                }

                $stage->update(['status' => \App\Models\TournamentStage::STATUS_IN_PROGRESS]);

                // Назначаем корты группам
                $groupCourts = $request->input('group_courts', []);
                $groupLabels = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
                foreach ($groups as $idx => $group) {
                    $label = $groupLabels[$idx] ?? (string)($idx + 1);
                    $courts = $groupCourts[$label] ?? [];
                    if (!empty($courts)) {
                        $group->update(['courts' => array_values($courts)]);
                    }
                }

                // Автогенерация расписания (если указано время начала)
                $scheduleStart = $request->input('schedule_start');
                if ($scheduleStart) {
                    $scheduleService = app(\App\Services\TournamentScheduleService::class);
                    $courts = array_values(array_filter($config['courts'] ?? ['Корт 1']));
                    $eventTz = $event->timezone ?: 'Europe/Moscow';
                    $scheduleService->generateSchedule(
                        $stage,
                        \Carbon\Carbon::parse($scheduleStart, $eventTz)->utc(),
                        (int) $request->input('schedule_match_duration', 30),
                        (int) $request->input('schedule_break_duration', 5),
                        $courts,
                    );
                }
            }
        }

        return $this->redirectToSetup($event, "Стадия \"{$stage->name}\" создана, жеребьёвка проведена.", false, "stage_{$stage->id}");
    }

    /* ================================================================
     *  Жеребьёвка
     * ================================================================ */

    public function draw(Request $request, Event $event)
    {
        $this->authorizeOrganizer($request, $event);

        $validated = $request->validate([
            'stage_id' => 'required|exists:tournament_stages,id',
            'mode'     => 'required|in:random,seeded,manual',
            'assignments' => 'nullable|array', // для manual: group_id => [team_ids]
        ]);

        $stage = TournamentStage::where('id', $validated['stage_id'])
            ->where('event_id', $event->id)
            ->firstOrFail();

        $occId = $stage->occurrence_id
            ?? (int) $request->input('occurrence_id', 0)
            ?: null;

        $teams = EventTeam::where('event_id', $event->id)
            ->when($occId, fn($q) => $q->where('occurrence_id', $occId))
            ->whereIn('status', ['submitted', 'approved', 'ready'])
            ->with('event')
            ->get();

        $groups = $stage->groups;

        // Для групп Hard/Lite: команды уже назначены — используем их
        $existingGroupTeamIds = DB::table('tournament_group_teams')
            ->whereIn('group_id', $groups->pluck('id'))
            ->pluck('team_id')
            ->unique();

        if ($existingGroupTeamIds->isNotEmpty()) {
            // Группа Hard/Lite — команды уже распределены, генерируем только матчи
            foreach ($groups as $group) {
                $this->setupService->generateRoundRobinMatches($stage, $group);
            }
            return $this->redirectToSetup($event, 'Матчи сгенерированы для группы.');
        }

        if ($teams->count() < 2) {
            return back()->with('error', 'Нужно минимум 2 подтверждённых команды.');
        }

        if (in_array($stage->type, ['round_robin', 'groups_playoff'])) {
            if ($groups->isEmpty()) {
                return back()->with('error', 'Сначала создайте группы.');
            }

            // Очистим старую жеребьёвку
            DB::table('tournament_group_teams')
                ->whereIn('group_id', $groups->pluck('id'))
                ->delete();

            if ($validated['mode'] === 'manual' && !empty($validated['assignments'])) {
                // Ручная: assignments[group_id] = [team_id, team_id, ...]
                $manualData = [];
                foreach ($validated['assignments'] as $groupId => $teamIds) {
                    $manualData[(int) $groupId] = array_map(fn($tid) => (int) $tid, (array) $teamIds);
                }
                $this->setupService->drawManual($manualData);
            } elseif ($validated['mode'] === 'random') {
                $this->setupService->drawRandom($groups, $teams);
            } else {
                $this->setupService->drawSeeded($groups, $teams);
            }

            // Инициализируем standings и генерируем матчи RR
            foreach ($groups as $group) {
                $this->setupService->initStandings($stage, $group);
                $this->setupService->generateRoundRobinMatches($stage, $group);
            }

            $stage->update(['status' => TournamentStage::STATUS_IN_PROGRESS]);

        } elseif ($stage->type === 'single_elim') {
            $teamIds = $teams->pluck('id')->toArray();
            if ($validated['mode'] === 'random') { shuffle($teamIds); }

            $thirdPlace = (bool) $stage->cfg('third_place_match', false);
            $this->bracketService->generateSingleElimination($stage, $teamIds, $thirdPlace);
            $stage->update(['status' => TournamentStage::STATUS_IN_PROGRESS]);

        } elseif ($stage->type === 'double_elim') {
            $teamIds = $teams->pluck('id')->toArray();
            if ($validated['mode'] === 'random') { shuffle($teamIds); }

            $this->bracketService->generateDoubleElimination($stage, $teamIds);
            $stage->update(['status' => TournamentStage::STATUS_IN_PROGRESS]);

        } elseif ($stage->type === 'swiss') {
            $teamIds = $teams->pluck('id')->toArray();
            if ($validated['mode'] === 'random') { shuffle($teamIds); }

            $this->swissService->initialize($stage, $teamIds);

        } elseif ($stage->type === 'king_of_court') {
            $teamIds = $teams->pluck('id')->toArray();
            if ($validated['mode'] === 'random') { shuffle($teamIds); }

            $this->kingService->initialize($stage, $teamIds);
            $this->kingService->generateNextMatch($stage);

        } elseif ($stage->type === TournamentStage::TYPE_KING_BEACH) {
            $playerIds = $this->resolveKingBeachPlayers($event, $occurrenceId, $request);
            if (count($playerIds) < 4) {
                return $this->redirectToSetup($event, 'Недостаточно игроков для King of the Beach (минимум 4).', true, "stage_{$stage->id}");
            }
            $this->kingBeachService->createRound($stage, $playerIds);
        }

        return $this->redirectToSetup($event, 'Жеребьёвка проведена, матчи сгенерированы.', false, "stage_{$stage->id}");
    }

    /* ================================================================
     *  Ввод счёта матча
     * ================================================================ */

    public function score(Request $request, TournamentMatch $match)
    {
        $stage = $match->stage;
        $event = $stage->event;
        $this->authorizeOrganizer($request, $event);

        // King of the Beach: отдельный обработчик (нет team_home/away_id)
        if ($stage->type === TournamentStage::TYPE_KING_BEACH) {
            return $this->scoreKingBeach($request, $match, $stage);
        }

        // Собираем только заполненные сеты (фильтруем пустые)
        $rawSets = $request->input('sets', []);
        $sets = [];
        foreach ($rawSets as $set) {
            $h = (int) ($set[0] ?? 0);
            $a = (int) ($set[1] ?? 0);
            if ($h > 0 || $a > 0) {
                $sets[] = [$h, $a];
            }
        }

        if (empty($sets)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Введите счёт хотя бы одного сета.'], 422);
            }
            return back()->with('error', 'Введите счёт хотя бы одного сета.');
        }

        try {
            $this->matchService->recordScore(
                $match,
                $sets,
                $request->user(),
            );

            try {
                app(\App\Services\TournamentStatsService::class)->rebuildAll($event);
            } catch (\Throwable $e) {
                \Log::warning('Stats rebuild failed: ' . $e->getMessage());
            }

            // Если это тайбрейк-матч — разрезолвим тайбрейкер и пересчитаем standings
            $freshMatch = $freshMatch ?? $match->fresh();
            if ($freshMatch->is_tiebreaker && $freshMatch->winner_team_id) {
                $tb = TournamentTiebreaker::where('match_id', $freshMatch->id)->first();
                if ($tb && $tb->status !== 'resolved') {
                    $tb->update([
                        'winner_team_id'      => $freshMatch->winner_team_id,
                        'resolved_by_user_id' => $request->user()->id,
                        'resolved_at'         => now(),
                        'status'              => 'resolved',
                    ]);
                    if ($freshMatch->group_id) {
                        $group = TournamentGroup::find($freshMatch->group_id);
                        if ($group) {
                            $this->standingsService->recalculateGroup($stage, $group);
                        }
                    }
                }
            }

            // Проверяем, завершена ли стадия
            $this->checkStageCompletion($stage);

            if ($request->expectsJson()) {
                return response()->json(['success' => true, 'match' => $match->fresh()]);
            }

            // Ищем следующий незавершённый матч этой стадии
            $nextMatch = TournamentMatch::where('stage_id', $stage->id)
                ->where('status', TournamentMatch::STATUS_SCHEDULED)
                ->whereNotNull('team_home_id')
                ->whereNotNull('team_away_id')
                ->orderBy('round')
                ->orderBy('match_number')
                ->first();

            if ($nextMatch) {
                return redirect()
                    ->route('tournament.matches.score.form', $nextMatch)
                    ->with('success', 'Счёт записан. Следующий матч:');
            }

            // Все матчи текущей стадии сыграны
            $occurrenceId = $stage->occurrence_id;

            // Для сезонных: проверяем только матчи текущего тура (occurrence)
            $stagesQuery = $event->tournamentStages();
            if ($occurrenceId) {
                $stagesQuery = $stagesQuery->where('occurrence_id', $occurrenceId);
            }
            $stageIds = $stagesQuery->pluck('id');

            $allDone = !TournamentMatch::whereIn('stage_id', $stageIds)
                ->where('status', TournamentMatch::STATUS_SCHEDULED)
                ->whereNotNull('team_home_id')
                ->whereNotNull('team_away_id')
                ->exists();

            if ($allDone && !$occurrenceId) {
                // Обычный турнир (не сезонный) — переход на итоги
                return redirect()
                    ->route('tournament.public.show', $event)
                    ->with('success', 'Все матчи завершены! Итоги турнира.');
            }

            return $this->redirectToSetup($event, 'Счёт записан. Этап завершён.');

        } catch (\InvalidArgumentException $e) {
            if ($request->expectsJson()) {
                return response()->json(['error' => $e->getMessage()], 422);
            }
            return back()->with('error', $e->getMessage());
        }
    }

    public function rescoreMatch(Request $request, TournamentMatch $match)
    {
        $stage = $match->stage;
        $event = $stage->event;
        $this->authorizeOrganizer($request, $event);

        // King of the Beach: редактирование счёта через scoreKingBeach
        if ($stage->type === TournamentStage::TYPE_KING_BEACH) {
            // Сбрасываем статус чтобы scoreKingBeach мог принять (он проверяет только данные)
            $match->update(['status' => TournamentMatch::STATUS_SCHEDULED]);
            return $this->scoreKingBeach($request, $match, $stage);
        }

        if (!$match->isCompleted()) {
            return back()->with('error', 'Матч не завершён — используйте обычный ввод счёта.');
        }

        $stageIsDivStage = str_starts_with($stage->name, 'Группа ');
        if (!$stageIsDivStage) {
            $hasDivStages = $event->tournamentStages()
                ->where('name', 'like', 'Группа %')
                ->where('occurrence_id', $stage->occurrence_id)
                ->exists();
            if ($hasDivStages) {
                return back()->with('error', 'Нельзя исправить счёт — группы уже сформированы. Откатите распределение и повторите.');
            }
        }

        $rawSets = $request->input('sets', []);
        $sets = [];
        foreach ($rawSets as $set) {
            $h = (int) ($set[0] ?? 0);
            $a = (int) ($set[1] ?? 0);
            if ($h > 0 || $a > 0) {
                $sets[] = [$h, $a];
            }
        }

        if (empty($sets)) {
            return back()->with('error', 'Введите счёт хотя бы одного сета.');
        }

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($match, $sets, $request, $stage) {
                $this->matchService->resetScore($match);
                $this->matchService->recordScore($match->fresh(), $sets, $request->user());
            });

            try {
                app(\App\Services\TournamentStatsService::class)->rebuildAll($event);
            } catch (\Throwable $e) {
                \Log::warning('Stats rebuild after rescore failed: ' . $e->getMessage());
            }

            $this->checkStageCompletion($stage);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return $this->redirectToSetup($event, 'Счёт матча #' . $match->match_number . ' исправлен, таблица пересчитана.');
    }

    /**
     * Форма ввода счёта (мобильная).
     */
    public function scoreForm(Request $request, TournamentMatch $match)
    {
        $stage = $match->stage;
        $event = $stage->event;
        $this->authorizeOrganizer($request, $event);

        $match->load(['teamHome.members.user', 'teamAway.members.user', 'stage']);

        return view('tournaments.score', compact('event', 'match', 'stage'));
    }

    /* ================================================================
     *  Продвижение в плей-офф
     * ================================================================ */

    /**
     * Сформировать группы (Hard/Lite) после группового этапа.
     */
    /**
     * Применить промоушен после завершения групп Hard/Lite.
     * Hard → все остаются. Lite → top-N остаются, остальные → резерв.
     */
    public function applyDivisionPromotion(Request $request, Event $event)
    {
        $this->authorizeOrganizer($request, $event);

        if (!$event->season_id) {
            return back()->with('error', 'Турнир не привязан к сезону.');
        }

        $occurrenceId = (int) $request->input('occurrence_id', 0);

        // Текущий сезон и дивизион по туру
        $seasonEvt = $occurrenceId > 0
            ? \App\Models\TournamentSeasonEvent::where('occurrence_id', $occurrenceId)->first()
            : null;
        $currentLeague = $seasonEvt?->league_id
            ? \App\Models\TournamentLeague::find($seasonEvt->league_id)
            : $event->season?->leagues()->first();

        if (!$currentLeague) {
            return back()->with('error', 'Дивизион не найден.');
        }

        // Стадии текущего тура (Hard/Lite/Medium группы)
        $stagesQuery = $event->tournamentStages()
            ->where('name', 'like', 'Группа %')
            ->where('name', '!=', 'Групповой этап');
        if ($occurrenceId > 0) {
            $stagesQuery->where('occurrence_id', $occurrenceId);
        }
        $stages = $stagesQuery->get();

        if ($stages->isEmpty()) {
            return back()->with('error', 'Группы (Hard/Lite) не найдены. Сначала сформируйте группы.');
        }
        if (!$stages->every(fn($s) => $s->status === 'completed')) {
            return back()->with('error', 'Не все группы завершены.');
        }

        // Существующие резервы ДО транзакции (для переноса в waitlist следующего тура)
        $existingReserveUserIds = \App\Models\TournamentLeagueTeam::where('league_id', $currentLeague->id)
            ->where('status', 'reserve')
            ->orderBy('reserve_position')
            ->pluck('user_id')
            ->filter()
            ->values()
            ->toArray();

        // Штрафной дедлайн: ближайшая суббота 07:00 МСК
        $penaltyExpiry = \Carbon\Carbon::now('Europe/Moscow')
            ->next(\Carbon\Carbon::SATURDAY)->setTime(7, 0, 0)->utc();

        // Команды → резерв (Lite bottom-2, Medium bottom-1)
        // relegatedTeams: [['team_id'=>X,'captain_id'=>Y,'substitution'=>?TeamSubstitution]]
        $relegatedTeams    = [];
        $reserveCaptainIds = [];  // капитаны со штрафом (идут в reserve)
        $penaltyCaptainIds = [];  // те же, но нужен confirmation_expires_at

        $collectRelegated = function (int $teamId) use ($occurrenceId, &$relegatedTeams, &$reserveCaptainIds, &$penaltyCaptainIds) {
            $team = \App\Models\EventTeam::find($teamId);
            if (!$team) return;
            $cap = $team->captain_user_id;
            if (!$cap) return;

            $sub = $occurrenceId > 0
                ? \App\Models\TeamSubstitution::where('team_id', $teamId)
                    ->where('occurrence_id', $occurrenceId)
                    ->where('status', 'confirmed')
                    ->first()
                : null;

            $relegatedTeams[] = ['team_id' => $teamId, 'captain_id' => $cap, 'substitution' => $sub];
            $reserveCaptainIds[] = $cap;
            $penaltyCaptainIds[] = $cap;
        };

        foreach ($stages->filter(fn($s) => str_contains($s->name, 'Lite')) as $stage) {
            foreach (\App\Models\TournamentStanding::where('stage_id', $stage->id)->orderBy('rank')->get() as $s) {
                if ($s->rank > 2) $collectRelegated($s->team_id);
            }
        }
        foreach ($stages->filter(fn($s) => str_contains($s->name, 'Medium')) as $stage) {
            foreach (\App\Models\TournamentStanding::where('stage_id', $stage->id)->orderBy('rank')->get() as $s) {
                if ($s->rank > 3) $collectRelegated($s->team_id);
            }
        }

        // Все капитаны из standings
        $allCaptainIds = $stages->flatMap(function ($stage) {
            return \App\Models\TournamentStanding::where('stage_id', $stage->id)
                ->join('event_teams', 'event_teams.id', '=', 'tournament_standings.team_id')
                ->pluck('event_teams.captain_user_id');
        })->unique()->filter()->values()->toArray();

        // Следующий сезон для cross-season промоушена
        $currentSeason = $seasonEvt?->season ?? $event->season;
        $nextSeason = $currentSeason
            ? \App\Models\TournamentSeason::where('league_id', $currentSeason->league_id)
                ->where('id', '>', $currentSeason->id)
                ->orderBy('id')
                ->first()
            : null;
        $nextLeague = $nextSeason
            ? \App\Models\TournamentLeague::where('season_id', $nextSeason->id)->first()
            : null;

        if ($nextLeague) {
            // Cross-season: переносим команды в следующий сезон
            $active = 0; $reserve = 0;

            \Illuminate\Support\Facades\DB::transaction(function () use (
                $nextLeague, $allCaptainIds, $reserveCaptainIds, $penaltyCaptainIds,
                $penaltyExpiry, $relegatedTeams, $currentLeague, &$active, &$reserve
            ) {
                foreach ($allCaptainIds as $captainId) {
                    $isReserve = in_array($captainId, $reserveCaptainIds);
                    $targetStatus = $isReserve ? 'reserve' : 'active';
                    $hasPenalty  = $isReserve && in_array($captainId, $penaltyCaptainIds);

                    $existing = \App\Models\TournamentLeagueTeam::where('league_id', $nextLeague->id)
                        ->where(function ($q) use ($captainId) {
                            $q->where('user_id', $captainId)
                              ->orWhereHas('team', fn($tq) => $tq->where('captain_user_id', $captainId));
                        })->first();

                    $attrs = [
                        'status'                  => $targetStatus,
                        'reserve_position'        => $isReserve ? $nextLeague->nextReservePosition() : null,
                        'left_at'                 => $isReserve ? now() : null,
                        'confirmation_expires_at' => $hasPenalty ? $penaltyExpiry : null,
                    ];

                    $existing ? $existing->update($attrs)
                              : \App\Models\TournamentLeagueTeam::create(array_merge($attrs, [
                                    'league_id' => $nextLeague->id,
                                    'user_id'   => $captainId,
                                    'team_id'   => null,
                                    'joined_at' => now(),
                                ]));

                    $isReserve ? $reserve++ : $active++;
                }

                // Отсутствовавший игрок (использовалась замена) → резерв БЕЗ штрафа
                foreach ($relegatedTeams as $rt) {
                    $sub = $rt['substitution'];
                    if (!$sub) continue;
                    $absentId = $sub->original_player_id;
                    if ($absentId === $rt['captain_id']) continue; // замена была сам капитан
                    \App\Models\TournamentLeagueTeam::firstOrCreate(
                        ['league_id' => $nextLeague->id, 'user_id' => $absentId],
                        [
                            'team_id'                 => null,
                            'status'                  => 'reserve',
                            'joined_at'               => now(),
                            'reserve_position'        => $nextLeague->nextReservePosition(),
                            'confirmation_expires_at' => null,
                        ]
                    );
                }
            });

            $waitlistAdded = $this->transferReserveToNextOccurrenceWaitlist(
                $event, $occurrenceId, $existingReserveUserIds, $reserveCaptainIds
            );
            $waitlistMsg = $waitlistAdded > 0 ? " + {$waitlistAdded} в лист ожидания тура →" : '';

            return back()->with('success',
                "Промоушен в {$nextSeason->name}: {$active} в основном составе, {$reserve} в резерве.{$waitlistMsg}");
        }

        // Fallback (нет следующего сезона): переводим в резерв текущего дивизиона
        $movedCount = 0;
        \Illuminate\Support\Facades\DB::transaction(function () use (
            $currentLeague, $reserveCaptainIds, $penaltyCaptainIds,
            $penaltyExpiry, $relegatedTeams, &$movedCount
        ) {
            foreach ($reserveCaptainIds as $captainId) {
                $hasPenalty = in_array($captainId, $penaltyCaptainIds);
                $lt = \App\Models\TournamentLeagueTeam::where('league_id', $currentLeague->id)
                    ->where(function ($q) use ($captainId) {
                        $q->where('user_id', $captainId)
                          ->orWhereHas('team', fn($tq) => $tq->where('captain_user_id', $captainId));
                    })->where('status', 'active')->first();
                if ($lt) {
                    $lt->update([
                        'status'                  => 'reserve',
                        'left_at'                 => now(),
                        'reserve_position'        => $currentLeague->nextReservePosition(),
                        'confirmation_expires_at' => $hasPenalty ? $penaltyExpiry : null,
                    ]);
                    $movedCount++;
                }
            }

            // Отсутствовавший игрок → резерв БЕЗ штрафа
            foreach ($relegatedTeams as $rt) {
                $sub = $rt['substitution'];
                if (!$sub) continue;
                $absentId = $sub->original_player_id;
                if ($absentId === $rt['captain_id']) continue;
                \App\Models\TournamentLeagueTeam::firstOrCreate(
                    ['league_id' => $currentLeague->id, 'user_id' => $absentId],
                    [
                        'team_id'                 => null,
                        'status'                  => 'reserve',
                        'joined_at'               => now(),
                        'reserve_position'        => $currentLeague->nextReservePosition(),
                        'confirmation_expires_at' => null,
                    ]
                );
            }
        });

        $waitlistAdded = $this->transferReserveToNextOccurrenceWaitlist(
            $event, $occurrenceId, $existingReserveUserIds, $reserveCaptainIds
        );
        $waitlistMsg = $waitlistAdded > 0 ? " + {$waitlistAdded} в лист ожидания следующего тура." : '';

        return back()->with('success', "Промоушен применён: {$movedCount} команд в резерв.{$waitlistMsg}");
    }

    public function formDivisions(Request $request, TournamentStage $stage)
    {
        $event = $stage->event;
        $this->authorizeOrganizer($request, $event);

        if (!$stage->isCompleted()) {
            return back()->with('error', 'Стадия ещё не завершена.');
        }

        $advancePerGroup = max(1, (int) $request->input('advance_per_group', 2));
        $groups = $stage->groups()->with(['standings' => fn($q) => $q->orderBy('rank')])->get();
        $groupsCount = $groups->count();

        if ($groupsCount < 2) {
            return back()->with('error', 'Нужно минимум 2 группы для распределения.');
        }

        // Определяем названия дивизионов
        $divisionNames = match($groupsCount) {
            2 => ['Hard', 'Lite'],
            3 => ['Hard', 'Medium', 'Lite'],
            default => array_merge(
                ['Hard'],
                array_map(fn($i) => 'Medium-' . $i, range(1, max(1, $groupsCount - 2))),
                ['Lite']
            ),
        };

        // Собираем standings по рангам с очками для умного распределения
        $byRank = []; // rank => [['team_id' => X, 'points' => Y, 'group_name' => Z], ...]
        foreach ($groups as $group) {
            foreach ($group->standings->sortBy('rank') as $standing) {
                $byRank[$standing->rank][] = [
                    'team_id' => $standing->team_id,
                    'points'  => $standing->rating_points,
                    'sets_diff' => $standing->sets_won - $standing->sets_lost,
                    'pts_diff' => $standing->points_scored - $standing->points_conceded,
                ];
            }
        }

        // Сортируем внутри каждого ранга по очкам (desc), потом по разнице сетов, потом очков
        foreach ($byRank as $rank => &$teams) {
            usort($teams, function($a, $b) {
                return $b['points'] <=> $a['points']
                    ?: $b['sets_diff'] <=> $a['sets_diff']
                    ?: $b['pts_diff'] <=> $a['pts_diff'];
            });
        }
        unset($teams);

        $hardTeamIds = [];
        $mediumTeamIds = [];
        $liteTeamIds = [];

        if ($groupsCount === 2) {
            // 2 группы: top-advance → Hard, остальные → Lite
            foreach ($groups as $group) {
                $standings = $group->standings->sortBy('rank')->values();
                foreach ($standings as $i => $standing) {
                    if ($i < $advancePerGroup) {
                        $hardTeamIds[] = $standing->team_id;
                    } else {
                        $liteTeamIds[] = $standing->team_id;
                    }
                }
            }
        } elseif ($groupsCount === 3) {
            // 3 группы: Hard = все 1-е + лучшее 2-е (= 4 команды)
            // Medium = оставшиеся 2-е + 2 лучших 3-х (= 4 команды)
            // Lite = все остальные

            // Все первые места → Hard
            $hardTeamIds = array_column($byRank[1] ?? [], 'team_id');

            // Вторые места: лучший → Hard, остальные → Medium
            $seconds = $byRank[2] ?? [];
            if (count($seconds) > 0) {
                $hardTeamIds[] = $seconds[0]['team_id']; // лучший 2-й → Hard
                for ($i = 1; $i < count($seconds); $i++) {
                    $mediumTeamIds[] = $seconds[$i]['team_id'];
                }
            }

            // Третьи места: 2 лучших → Medium, остальные → Lite
            $thirds = $byRank[3] ?? [];
            $thirdToMedium = min(2, count($thirds));
            for ($i = 0; $i < count($thirds); $i++) {
                if ($i < $thirdToMedium) {
                    $mediumTeamIds[] = $thirds[$i]['team_id'];
                } else {
                    $liteTeamIds[] = $thirds[$i]['team_id'];
                }
            }

            // Четвёртые и дальше → Lite
            foreach ($byRank as $rank => $teams) {
                if ($rank <= 3) continue;
                foreach ($teams as $t) {
                    $liteTeamIds[] = $t['team_id'];
                }
            }
        } else {
            // 4+ групп: Hard = 1-е места + лучшие 2-е, далее по рангам
            $allTeamsByQuality = [];
            foreach ($byRank as $rank => $teams) {
                foreach ($teams as $t) {
                    $allTeamsByQuality[] = array_merge($t, ['rank' => $rank]);
                }
            }
            // Уже отсортированы по рангу + внутри ранга по очкам
            $perDiv = (int) ceil(count($allTeamsByQuality) / count($divisionNames));
            $chunks = array_chunk($allTeamsByQuality, $perDiv);
            
            $hardTeamIds = array_column($chunks[0] ?? [], 'team_id');
            $liteTeamIds = array_column(end($chunks) ?: [], 'team_id');
            // Средние группы
            for ($i = 1; $i < count($chunks) - 1; $i++) {
                foreach ($chunks[$i] as $t) {
                    $mediumTeamIds[] = $t['team_id'];
                }
            }
        }

        // occurrence_id из текущей стадии или из query
        $occurrenceId = $stage->occurrence_id;

        // Форматы матчей для групп
        $divFormats = [
            'Hard'   => $request->input('div_format_hard') ?: null,
            'Medium' => $request->input('div_format_medium') ?: null,
            'Lite'   => $request->input('div_format_lite') ?: null,
        ];

        $setupService = app(\App\Services\TournamentSetupService::class);

        \Illuminate\Support\Facades\DB::transaction(function () use (
            $event, $setupService, $divisionNames, $hardTeamIds, $liteTeamIds, $mediumTeamIds, $stage, $occurrenceId, $divFormats, $request
        ) {
            // Удаляем ранее созданные дивизионные стадии (Hard/Medium/Lite) перед пересозданием
            $existing = $event->tournamentStages()
                ->where('name', 'like', 'Группа %')
                ->where('name', '!=', 'Групповой этап')
                ->where('occurrence_id', $stage->occurrence_id)
                ->get();
            foreach ($existing as $ex) {
                foreach ($ex->groups as $grp) {
                    $grp->standings()->delete();
                    \App\Models\TournamentMatch::where('group_id', $grp->id)->delete();
                    \App\Models\TournamentGroupTeam::where('group_id', $grp->id)->delete();
                    $grp->delete();
                }
                $ex->delete();
            }

            $divisions = [
                'Hard' => $hardTeamIds,
                'Medium' => $mediumTeamIds,
                'Lite' => $liteTeamIds,
            ];

            $sortOrder = ($event->tournamentStages()->max('sort_order') ?? 0) + 1;

            foreach ($divisionNames as $divName) {
                $teamIds = $divisions[$divName] ?? ($divisions[explode('-', $divName)[0]] ?? []);
                if (empty($teamIds)) continue;

                // Создаём стадию-группу (Round Robin внутри)
                $divStage = $setupService->createStage($event, [
                    'type'          => 'round_robin',
                    'name'          => 'Группа ' . $divName,
                    'sort_order'    => $sortOrder++,
                    'occurrence_id' => $occurrenceId,
                    'config'        => array_merge($stage->config ?? [],
                        !empty($divFormats[$divName]) ? ['match_format' => $divFormats[$divName]] : []
                    ), // наследуем формат + override для группы
                ]);

                // Создаём одну группу внутри стадии
                $group = $setupService->createGroups($divStage, 1, [$divName])->first();

                // Назначаем команды
                foreach ($teamIds as $seed => $teamId) {
                    \App\Models\TournamentGroupTeam::create([
                        'group_id' => $group->id,
                        'team_id'  => $teamId,
                        'seed'     => $seed + 1,
                    ]);
                }

                // Генерируем матчи Round Robin (standings создаются внутри)
                $this->setupService->generateRoundRobinMatches($divStage, $group);

                // Назначаем площадки группе
                $courtKey = 'div_courts_' . strtolower($divName);
                $divCourts = $request->input($courtKey, []);
                if (!empty($divCourts)) {
                    $group->update(['courts' => array_values($divCourts)]);
                }

                // Автогенерация расписания (если указано время начала)
                $scheduleStart = $request->input('schedule_start');
                if ($scheduleStart) {
                    $courtsForSchedule = array_values($divCourts ?: $stage->cfg('courts', []));
                    $eventTz = $event->timezone ?: 'Europe/Moscow';
                    app(\App\Services\TournamentScheduleService::class)->generateSchedule(
                        $divStage,
                        \Carbon\Carbon::parse($scheduleStart, $eventTz)->utc(),
                        (int) $request->input('schedule_match_duration', 30),
                        (int) $request->input('schedule_break_duration', 5),
                        $courtsForSchedule,
                    );
                }
            }
        });

        return $this->redirectToSetup($event, 'Группы сформированы: ' . implode(', ', $divisionNames), false, 'promotion_block');
    }

    public function advance(Request $request, TournamentStage $stage)
    {
        $event = $stage->event;
        $this->authorizeOrganizer($request, $event);

        if (! $stage->isCompleted()) {
            return back()->with('error', 'Групповая стадия ещё не завершена.');
        }

        $validated = $request->validate([
            'playoff_stage_id' => 'required|exists:tournament_stages,id',
            'advance_per_group' => 'required|integer|min:1|max:8',
        ]);

        $playoffStage = TournamentStage::where('id', $validated['playoff_stage_id'])
            ->where('event_id', $event->id)
            ->firstOrFail();

        try {
            $this->bracketService->advanceToPlayoff(
                $stage, $playoffStage,
                (int) $validated['advance_per_group'],
                $this->standingsService,
            );

            $playoffStage->update(['status' => TournamentStage::STATUS_IN_PROGRESS]);

            // Уведомляем продвинувшиеся команды
            try {
                $advancedTeamIds = TournamentMatch::where('stage_id', $playoffStage->id)
                    ->whereNotNull('team_home_id')
                    ->pluck('team_home_id')
                    ->merge(
                        TournamentMatch::where('stage_id', $playoffStage->id)
                            ->whereNotNull('team_away_id')
                            ->pluck('team_away_id')
                    )
                    ->unique();

                $notificationService = app(\App\Services\TournamentNotificationService::class);
                foreach ($advancedTeamIds as $teamId) {
                    $team = EventTeam::find($teamId);
                    if ($team) {
                        $notificationService->notifyAdvancement($team, $event, $playoffStage->name);
                    }
                }
            } catch (\Throwable $e) {
                \Log::warning('Advancement notification failed: ' . $e->getMessage());
            }

            return $this->redirectToSetup($event, 'Команды продвинуты в плей-офф.');

        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /* ================================================================
     *  Удаление стадии (reset)
     * ================================================================ */


    /**
     * Следующий тур (Swiss) или следующий матч (King of the Court).
     */
    public function nextRound(Request $request, TournamentStage $stage)
    {
        $event = $stage->event;
        $this->authorizeOrganizer($request, $event);

        try {
            if ($stage->type === 'swiss') {
                $matches = $this->swissService->generateNextRound($stage);
                return $this->redirectToSetup($event, 'Тур ' . $matches->first()->round . ' сгенерирован (' . $matches->count() . ' матчей).');

            } elseif ($stage->type === 'king_of_court') {
                $match = $this->kingService->generateNextMatch($stage);
                if (!$match) {
                    return back()->with('error', 'Нет больше соперников в очереди.');
                }
                return $this->redirectToSetup($event, 'Следующий матч King of the Court создан.');

            } elseif ($stage->type === TournamentStage::TYPE_KING_BEACH) {
                $nextStage = $this->kingBeachService->advanceToNextRound($stage);
                if (!$nextStage) {
                    return back()->with('error', 'Недостаточно игроков для следующего раунда (нужно минимум 4).');
                }
                return $this->redirectToSetup($event, 'Следующий раунд King of the Beach создан.', false, "stage_{$nextStage->id}");
            }

            return back()->with('error', 'Действие недоступно для этого типа стадии.');

        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    
    /**
     * Откат стадии — сброс всех матчей и standings с сохранением структуры.
     */
    public function revertStage(Request $request, TournamentStage $stage)
    {
        $event = $stage->event;
        $this->authorizeOrganizer($request, $event);

        // King of the Beach: полный сброс (группы + матчи + standings)
        if ($stage->type === TournamentStage::TYPE_KING_BEACH) {
            $this->kingBeachService->revertStage($stage);
            return $this->redirectToSetup($stage->event, 'Стадия King of the Beach сброшена.', false, "stage_{$stage->id}");
        }

        DB::transaction(function () use ($stage) {
            // Удаляем связанные группы Hard/Lite (если это групповой этап)
            if (in_array($stage->type, ['round_robin', 'groups_playoff'])) {
                $divQuery = $stage->event->tournamentStages()
                    ->where('id', '!=', $stage->id)
                    ->where('name', 'like', 'Группа %');
                if ($stage->occurrence_id) {
                    $divQuery->where('occurrence_id', $stage->occurrence_id);
                }
                $divQuery->get()->each(fn($ds) => $ds->delete());
            }

            // Сбрасываем все матчи
            $stage->matches()->update([
                'status'            => TournamentMatch::STATUS_SCHEDULED,
                'winner_team_id'    => null,
                'score_home'        => null,
                'score_away'        => null,
                'sets_home'         => 0,
                'sets_away'         => 0,
                'total_points_home' => 0,
                'total_points_away' => 0,
                'scored_by_user_id' => null,
                'scored_at'         => null,
            ]);

            // Для bracket — очищаем team_id из матчей раунда > 1 (кроме single_elim первого раунда)
            if (in_array($stage->type, ['single_elim', 'double_elim'])) {
                $stage->matches()->where('round', '>', 1)->update([
                    'team_home_id' => null,
                    'team_away_id' => null,
                ]);
            }

            // Обнуляем standings
            TournamentStanding::where('stage_id', $stage->id)->update([
                'played' => 0, 'wins' => 0, 'losses' => 0, 'draws' => 0,
                'sets_won' => 0, 'sets_lost' => 0,
                'points_scored' => 0, 'points_conceded' => 0,
                'rating_points' => 0, 'rank' => 0,
            ]);

            // Удаляем player stats для этого турнира
            \App\Models\PlayerTournamentStats::where('event_id', $stage->event_id)->delete();

            // Пересчитываем season stats
            if ($stage->event->season_id) {
                $season = $stage->event->season;
                if ($season) {
                    app(\App\Services\TournamentSeasonStatsService::class)
                        ->rebuildForSeason($season);
                }
            }

            $stage->update(['status' => TournamentStage::STATUS_IN_PROGRESS]);
        });

        return $this->redirectToSetup($event, "Стадия \"{$stage->name}\" откачена — все счета сброшены.");
    }

        public function destroyStage(Request $request, TournamentStage $stage)
    {
        $event = $stage->event;
        $this->authorizeOrganizer($request, $event);

        $name = $stage->name;
        $divNames = '';

        // Если удаляем групповой этап — удалить и связанные группы Hard/Lite
        if (in_array($stage->type, ['round_robin', 'groups_playoff'])) {
            $divStages = $event->tournamentStages()
                ->where('id', '!=', $stage->id)
                ->where(function($q) {
                    $q->where('name', 'like', 'Группа %');
                });
            if ($stage->occurrence_id) {
                $divStages->where('occurrence_id', $stage->occurrence_id);
            }
            $deleted = $divStages->get();
            foreach ($deleted as $ds) {
                $ds->delete();
            }
            $divNames = $deleted->pluck('name')->implode(', ');
        }

        $stage->delete(); // cascadeOnDelete очистит groups, matches, standings

        $msg = "Стадия \"{$name}\" удалена.";
        if (!empty($divNames)) {
            $msg .= " Также удалены: {$divNames}.";
        }

        return $this->redirectToSetup($event, $msg);
    }

    /* ================================================================
     *  Helpers
     * ================================================================ */


    /**
     * Загрузка фото турнира.
     */

    /**
     * Установить MVP турнира.
     */

    /**
     * Сгенерировать расписание для стадии.
     */
    public function assignCourts(Request $request, TournamentStage $stage)
    {
        $event = $stage->event;
        $this->authorizeOrganizer($request, $event);

        $courtAssignments = $request->input('group_courts', []);

        foreach ($stage->groups as $group) {
            $courts = $courtAssignments[$group->id] ?? [];
            $group->update(['courts' => array_values(array_filter($courts))]);
        }

        return back()->with('success', 'Площадки назначены.');
    }

    public function generateSchedule(Request $request, TournamentStage $stage)
    {
        $event = $stage->event;
        $this->authorizeOrganizer($request, $event);

        $validated = $request->validate([
            'start_time'     => 'required|date',
            'match_duration' => 'required|integer|min:15|max:180',
            'break_duration' => 'required|integer|min:0|max:60',
        ]);

        $scheduleService = app(\App\Services\TournamentScheduleService::class);
        $courts = $stage->cfg('courts', ['Корт 1']);

        $eventTz = $event->timezone ?: 'Europe/Moscow';
        $count = $scheduleService->generateSchedule(
            $stage,
            \Carbon\Carbon::parse($validated['start_time'], $eventTz)->utc(),
            (int) $validated['match_duration'],
            (int) $validated['break_duration'],
            $courts,
        );

        return $this->redirectToSetup($event, "Расписание сгенерировано: {$count} матчей.");
    }

        public function setMvp(Request $request, Event $event)
    {
        $this->authorizeOrganizer($request, $event);

        $validated = $request->validate([
            'mvp_user_id' => 'required|exists:users,id',
        ]);

        $event->update(['tournament_mvp_user_id' => $validated['mvp_user_id']]);

        return $this->redirectToSetup($event, 'MVP турнира установлен.');
    }

        public function uploadPhotos(Request $request, Event $event)
    {
        $this->authorizeOrganizer($request, $event);

        // Режим 1: выбор из галереи пользователя
        if ($request->has('photo_ids')) {
            $photoIds = json_decode($request->input('photo_ids', '[]'), true);
            if (!is_array($photoIds) || empty($photoIds)) {
                return back()->with('error', 'Выберите хотя бы одно фото.');
            }

            // Удаляем старые tournament_photos
            $event->clearMediaCollection('tournament_photos');

            // Копируем выбранные фото
            $count = 0;
            foreach ($photoIds as $mediaId) {
                $source = \Spatie\MediaLibrary\MediaCollections\Models\Media::find($mediaId);
                if ($source && file_exists($source->getPath())) {
                    $event->addMedia($source->getPath())
                        ->preservingOriginal()
                        ->toMediaCollection('tournament_photos');
                    $count++;
                }
            }

            try {
                app(\App\Services\TournamentNotificationService::class)->notifyPhotosAdded($event);
            } catch (\Throwable $e) {
                \Log::warning('Photo notification failed: ' . $e->getMessage());
            }

            return $this->redirectToSetup($event, "Фото обновлены ({$count} шт.)");
        }

        // Режим 2: прямая загрузка файлов
        $request->validate([
            'photos'   => 'required|array|min:1|max:20',
            'photos.*' => 'image|mimes:jpg,jpeg,png,webp|max:10240',
        ]);

        foreach ($request->file('photos') as $photo) {
            $event->addMedia($photo)->toMediaCollection('tournament_photos');
        }

        // Уведомляем участников
        try {
            app(\App\Services\TournamentNotificationService::class)->notifyPhotosAdded($event);
        } catch (\Throwable $e) {
            \Log::warning('Photo notification failed: ' . $e->getMessage());
        }

        return $this->redirectToSetup($event, 'Фото загружены (' . count($request->file('photos')) . ' шт.)');
    }

    /**
     * Удаление фото турнира.
     */
    public function deletePhoto(Request $request, Event $event, $mediaId)
    {
        $this->authorizeOrganizer($request, $event);

        $media = $event->getMedia('tournament_photos')->firstWhere('id', $mediaId);
        if ($media) {
            $media->delete();
        }

        return $this->redirectToSetup($event, 'Фото удалено.');
    }

    public function approveApplication(Request $request, Event $event, EventTeamApplication $application): RedirectResponse
    {
        $this->authorizeOrganizer($request, $event);
        abort_unless((int) $application->event_id === (int) $event->id, 404);
        abort_unless(in_array($application->status, ['pending', 'incomplete'], true), 422, 'Заявка уже обработана.');

        $application->update([
            'status' => 'approved',
            'reviewed_by_user_id' => $request->user()->id,
            'reviewed_at' => now(),
            'decision_comment' => 'Одобрено организатором',
        ]);

        $application->team?->update(['status' => 'approved']);

        // Автодобавление в лигу сезона
        if ($event->season_id && $application->team) {
            $this->syncTeamToLeague($event, $application->team);
        }

        return back()->with('success', "Заявка команды «{$application->team->name}» одобрена ✅");
    }

    public function rejectApplication(Request $request, Event $event, EventTeamApplication $application): RedirectResponse
    {
        $this->authorizeOrganizer($request, $event);
        abort_unless((int) $application->event_id === (int) $event->id, 404);
        abort_unless(in_array($application->status, ['pending', 'incomplete'], true), 422, 'Заявка уже обработана.');

        $validated = $request->validate([
            'rejection_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $application->update([
            'status' => 'rejected',
            'reviewed_by_user_id' => $request->user()->id,
            'reviewed_at' => now(),
            'rejection_reason' => $validated['rejection_reason'] ?? null,
        ]);

        $application->team?->update(['status' => 'rejected']);

        return back()->with('success', "Заявка команды «{$application->team->name}» отклонена.");
    }

    /**
     * Добавить команду в лигу сезона (если ещё не добавлена).
     */
    public function syncAllTeamsToLeague(Request $request, Event $event)
    {
        $this->authorizeOrganizer($request, $event);

        if (!$event->season_id) {
            return back()->with('error', 'Событие не привязано к сезону.');
        }

        $occurrenceId = (int) $request->input('occurrence_id', 0);

        // Правильный league — по туру (тур может принадлежать другому сезону)
        $seasonEvt = $occurrenceId > 0
            ? \App\Models\TournamentSeasonEvent::where('occurrence_id', $occurrenceId)->first()
            : null;
        $league = $seasonEvt?->league_id
            ? \App\Models\TournamentLeague::find($seasonEvt->league_id)
            : $event->season?->leagues()->first();

        if (!$league) {
            return back()->with('error', 'В сезоне нет дивизионов.');
        }
        $added = 0;
        $linked = 0;

        // Направление 1: EventTeam тура → Лига (добавляем новых участников)
        // Исключаем rejected — иначе отклонённые команды попадут в лигу
        $teams = EventTeam::where('event_id', $event->id)
            ->when($occurrenceId > 0, fn($q) => $q->where('occurrence_id', $occurrenceId))
            ->whereIn('status', ['submitted', 'approved'])
            ->get();

        foreach ($teams as $team) {
            $exists = \App\Models\TournamentLeagueTeam::where('league_id', $league->id)
                ->where('team_id', $team->id)
                ->exists();

            if (!$exists) {
                \App\Models\TournamentLeagueTeam::create([
                    'league_id'  => $league->id,
                    'team_id'    => $team->id,
                    'user_id'    => $team->captain_user_id,
                    'status'     => $league->hasCapacity() ? 'active' : 'reserve',
                    'joined_at'  => now(),
                    'reserve_position' => $league->hasCapacity() ? null : $league->nextReservePosition(),
                ]);
                $added++;
            }
        }

        // Направление 2: Участники лиги (active + reserve) → EventTeam тура (создаём если нет)
        if ($occurrenceId > 0) {
            $occurrence = \App\Models\EventOccurrence::find($occurrenceId);
            if ($occurrence) {
                $activeLeagueTeams = \App\Models\TournamentLeagueTeam::where('league_id', $league->id)
                    ->whereIn('status', ['active', 'pending_confirmation', 'reserve'])
                    ->with('team.members')
                    ->get();

                foreach ($activeLeagueTeams as $lt) {
                    $captainId = $lt->team?->captain_user_id ?? $lt->user_id;
                    if (!$captainId) continue;

                    // Уже есть EventTeam для этого капитана в этом туре?
                    $existing = EventTeam::where('event_id', $event->id)
                        ->where('occurrence_id', $occurrenceId)
                        ->where('captain_user_id', $captainId)
                        ->first();

                    if ($existing) {
                        // Обновляем ссылку если нужно
                        if ((int) $lt->team_id !== $existing->id) {
                            $lt->update(['team_id' => $existing->id]);
                        }
                        continue;
                    }

                    $oldTeam = $lt->team;

                    // Создаём новый EventTeam
                    $baseName = $oldTeam?->name
                        ?? (\App\Models\User::find($captainId)?->last_name ?? 'Команда');
                    $name = $baseName;
                    $i = 2;
                    while (EventTeam::where('event_id', $event->id)
                        ->where('occurrence_id', $occurrenceId)
                        ->where('name', $name)->exists()) {
                        $name = $baseName . ' ' . $i++;
                    }

                    $newTeam = EventTeam::create([
                        'event_id'        => $event->id,
                        'occurrence_id'   => $occurrenceId,
                        'captain_user_id' => $captainId,
                        'name'            => $name,
                        'team_kind'       => $oldTeam?->team_kind ?? 'beach_pair',
                        'status'          => 'approved',
                        'invite_code'     => \Illuminate\Support\Str::random(8),
                        'is_complete'     => (bool) $oldTeam?->is_complete,
                        'last_checked_at' => now(),
                        'confirmed_at'    => now(),
                    ]);

                    // Копируем состав из предыдущего тура
                    if ($oldTeam && $oldTeam->members->isNotEmpty()) {
                        foreach ($oldTeam->members as $member) {
                            \App\Models\EventTeamMember::create([
                                'event_team_id'       => $newTeam->id,
                                'user_id'             => $member->user_id,
                                'role_code'           => $member->role_code,
                                'team_role'           => $member->team_role,
                                'position_code'       => $member->position_code,
                                'position_order'      => $member->position_order,
                                'confirmation_status' => 'confirmed',
                                'joined_at'           => now(),
                                'responded_at'        => now(),
                                'confirmed_at'        => now(),
                            ]);
                        }
                    }

                    $lt->update(['team_id' => $newTeam->id, 'status' => 'active']);
                    $linked++;
                }
            }
        }

        $total = $added + $linked;
        if ($total === 0) {
            return back()->with('success', 'Все команды уже синхронизированы.');
        }

        return back()->with('success', "Синхронизация: добавлено в лигу {$added}, добавлено в тур {$linked}.");
    }

    private function syncTeamToLeague(Event $event, EventTeam $team): void
    {
        // Правильный league — по occurrence команды (тур может принадлежать другому сезону)
        $seasonEvt = $team->occurrence_id
            ? \App\Models\TournamentSeasonEvent::where('occurrence_id', $team->occurrence_id)->first()
            : null;
        $league = $seasonEvt?->league_id
            ? \App\Models\TournamentLeague::find($seasonEvt->league_id)
            : $event->season?->leagues()->first();
        if (!$league) return;

        // Проверяем — уже есть?
        $exists = \App\Models\TournamentLeagueTeam::where('league_id', $league->id)
            ->where('team_id', $team->id)
            ->exists();

        if ($exists) return;

        // Проверяем capacity
        if (!$league->hasCapacity()) {
            // Добавляем в резерв
            \App\Models\TournamentLeagueTeam::create([
                'league_id'        => $league->id,
                'team_id'          => $team->id,
                'user_id'          => $team->captain_user_id,
                'status'           => 'reserve',
                'joined_at'        => now(),
                'reserve_position' => $league->nextReservePosition(),
            ]);
            return;
        }

        \App\Models\TournamentLeagueTeam::create([
            'league_id'  => $league->id,
            'team_id'    => $team->id,
            'user_id'    => $team->captain_user_id,
            'status'     => 'active',
            'joined_at'  => now(),
        ]);
    }

    /**
     * Redirect to setup preserving occurrence_id.
     */
    private function redirectToSetup(Event $event, ?string $message = null, bool $isError = false, ?string $anchor = null)
    {
        $occId = request()->input('occurrence_id')
            ?: request()->query('occurrence_id')
            ?: null;

        // Если нет в request — попробуем из referer
        if (!$occId) {
            $referer = request()->header('referer', '');
            if (preg_match('/occurrence_id=(\d+)/', $referer, $m)) {
                $occId = $m[1];
            }
        }

        // Если всё ещё нет — из selectedOccurrence (для сезонных)
        if (!$occId && $event->season_id) {
            $firstOcc = $event->occurrences()
                ->whereNull('cancelled_at')
                ->orderBy('starts_at')
                ->first();
            if ($firstOcc) {
                $occId = $firstOcc->id;
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
     * Получить список игроков для king_beach жеребьёвки.
     * Приоритет: manual_player_ids из request → event_registrations.
     */
    private function resolveKingBeachPlayers(Event $event, ?int $occurrenceId, Request $request): array
    {
        // Ручная передача (тестирование / события без individual-регистрации)
        $manual = array_filter(array_map('intval', (array) $request->input('manual_player_ids', [])));
        if (!empty($manual)) {
            return array_values($manual);
        }

        return DB::table('event_registrations')
            ->where('event_id', $event->id)
            ->when($occurrenceId, fn($q) => $q->where('occurrence_id', $occurrenceId))
            ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id')
            ->map(fn($id) => (int) $id)
            ->toArray();
    }

    /**
     * Ввод счёта партии king_beach (без team_home/away_id).
     */
    private function scoreKingBeach(Request $request, TournamentMatch $match, TournamentStage $stage): RedirectResponse
    {
        $rawSets   = $request->input('sets', []);
        $homeScore = (int) ($rawSets[0][0] ?? 0);
        $awayScore = (int) ($rawSets[0][1] ?? 0);

        if ($homeScore === 0 && $awayScore === 0) {
            return back()->with('error', 'Введите счёт партии.');
        }
        if ($homeScore === $awayScore) {
            return back()->with('error', 'Ничья невозможна в king_beach партии.');
        }

        $target = $stage->setPoints();
        $winner = max($homeScore, $awayScore);
        $loser  = min($homeScore, $awayScore);

        if ($winner < $target) {
            return back()->with('error', "Победитель должен набрать минимум {$target} очков (сейчас {$winner}).");
        }
        if ($winner - $loser < 2) {
            return back()->with('error', "Разница должна быть минимум 2 очка ({$homeScore}:{$awayScore}).");
        }
        if ($loser >= $target - 1 && $winner - $loser !== 2) {
            return back()->with('error', "При тай-брейке разница должна быть ровно 2 очка ({$homeScore}:{$awayScore}).");
        }
        if ($loser < $target - 1 && $winner !== $target) {
            return back()->with('error', "Победитель должен набрать ровно {$target}, а не {$winner}.");
        }

        DB::transaction(function () use ($match, $homeScore, $awayScore, $request) {
            $match->update([
                'score_home'        => [$homeScore],
                'score_away'        => [$awayScore],
                'sets_home'         => $homeScore > $awayScore ? 1 : 0,
                'sets_away'         => $awayScore > $homeScore ? 1 : 0,
                'total_points_home' => $homeScore,
                'total_points_away' => $awayScore,
                'status'            => TournamentMatch::STATUS_COMPLETED,
                'scored_by_user_id' => $request->user()?->id,
                'scored_at'         => now(),
            ]);

            if ($match->group_id) {
                $this->kingBeachService->recalculateGroupStandings($match->group);
                $this->checkStageCompletion($match->stage);
            }
        });

        return $this->redirectToSetup(
            $stage->event,
            'Счёт сохранён.',
            false,
            "stage_{$stage->id}"
        );
    }

            private function authorizeOrganizer(Request $request, Event $event): void
    {
        $user = $request->user();
        if (! $user) abort(403);

        $isAdmin = $user->isAdmin();
        $isOwner = (int) $event->organizer_id === (int) $user->id;
        $isStaff = $user->isStaff() && (int) $user->getOrganizerIdForStaff() === (int) $event->organizer_id;

        if (! $isAdmin && ! $isOwner && ! $isStaff) {
            abort(403, 'Нет прав на управление турниром.');
        }
    }

    private function checkStageCompletion(TournamentStage $stage): void
    {
        $total = $stage->matches()
            ->whereNotIn('status', [TournamentMatch::STATUS_CANCELLED])
            ->count();

        $completed = $stage->matches()
            ->where('status', TournamentMatch::STATUS_COMPLETED)
            ->count();

        if ($total > 0 && $total === $completed) {
            $stage->update(['status' => TournamentStage::STATUS_COMPLETED]);

            // Проверяем завершение
            $event = $stage->event;

            // Для сезонных турниров: НЕ отправляем "турнир завершён"
            // Каждый тур — отдельный цикл, завершение управляется промоушеном
            if ($event->season_id) {
                // Проверяем только: нужно ли формировать группы Hard/Lite?
                $occId = $stage->occurrence_id;
                $occStages = $event->tournamentStages()->where('occurrence_id', $occId);
                $allOccCompleted = $occStages->count() > 0
                    && $occStages->where('status', TournamentStage::STATUS_COMPLETED)->count() === $occStages->count();

                // Ничего не делаем — организатор сам нажимает "Применить промоушен"
                return;
            }

            $allStages = $event->tournamentStages()->count();
            $completedStages = $event->tournamentStages()
                ->where('status', TournamentStage::STATUS_COMPLETED)
                ->count();

            if ($allStages > 0 && $allStages === $completedStages) {
                try {
                    app(\App\Services\TournamentNotificationService::class)
                        ->notifyTournamentCompleted($event);

                    // Пересчитываем career stats
                    app(\App\Services\TournamentStatsService::class)
                        ->rebuildAllCareerStatsForEvent($event);

                    // Обновляем Elo
                    app(\App\Services\TournamentEloService::class)
                        ->recalculateForEvent($event);

                    // Авто-продвижение в сезоне (promote/relegate/reserve)
                    app(\App\Services\TournamentPromotionService::class)
                        ->processEvent($event);
                } catch (\Throwable $e) {
                    \Log::warning('Tournament completion notification failed: ' . $e->getMessage());
                }
            }
        }
    }

    /* ================================================================
     *  Детальная статистика игроков
     * ================================================================ */

    /**
     * Начать заполнение результатов — редирект к первому незаполненному матчу.
     */
    public function startScoring(Request $request, Event $event)
    {
        $this->authorizeOrganizer($request, $event);

        $occurrenceId = $request->query('occurrence_id');
        $stageQuery = $event->tournamentStages();
        if ($occurrenceId) {
            $stageQuery->where('occurrence_id', $occurrenceId);
        }
        $stageIds = $stageQuery->pluck('id');

        $nextMatch = TournamentMatch::whereIn('stage_id', $stageIds)
            ->where('status', TournamentMatch::STATUS_SCHEDULED)
            ->whereNotNull('team_home_id')
            ->whereNotNull('team_away_id')
            ->orderBy('stage_id')
            ->orderBy('round')
            ->orderBy('match_number')
            ->first();

        if (!$nextMatch) {
            return redirect()
                ->route('tournament.public.show', $event)
                ->with('success', 'Все матчи уже завершены.');
        }

        return redirect()->route('tournament.matches.score.form', $nextMatch);
    }

    /**
     * Форма ввода детальной статистики матча.
     */
    public function playerStatsForm(Request $request, TournamentMatch $match)
    {
        $stage = $match->stage;
        $event = $stage->event;
        $this->authorizeOrganizer($request, $event);

        if (!$match->isCompleted()) {
            return redirect()
                ->route('tournament.setup', $event)
                ->with('error', 'Сначала введите счёт матча.');
        }

        $match->load(['teamHome.members.user', 'teamAway.members.user', 'stage']);

        $playerStatsService = app(PlayerMatchStatsService::class);
        $players = $playerStatsService->getMatchPlayers($match);
        $existingStats = $playerStatsService->getMatchStatsTable($match);

        $setsCount = is_array($match->score_home) ? count($match->score_home) : 0;
        $statFields = PlayerMatchStatsService::STAT_FIELDS;

        return view('tournaments.player-stats', compact(
            'event', 'match', 'stage', 'players',
            'existingStats', 'setsCount', 'statFields'
        ));
    }

    /**
     * Сохранение детальной статистики матча.
     */
    public function playerStatsSave(Request $request, TournamentMatch $match)
    {
        $stage = $match->stage;
        $event = $stage->event;
        $this->authorizeOrganizer($request, $event);

        if (!$match->isCompleted()) {
            return back()->with('error', 'Сначала введите счёт матча.');
        }

        $playerStatsService = app(PlayerMatchStatsService::class);

        // Формат: stats[teamId][userId][setNumber][field] = value
        $allStats = $request->input('stats', []);

        DB::transaction(function () use ($allStats, $match, $playerStatsService) {
            foreach ($allStats as $teamId => $players) {
                foreach ($players as $userId => $sets) {
                    foreach ($sets as $setNumber => $data) {
                        $playerStatsService->saveSetStats(
                            $match,
                            (int) $setNumber,
                            (int) $userId,
                            (int) $teamId,
                            $data
                        );
                    }
                }
            }
        });

        // Агрегируем в tournament stats и career stats
        try {
            $playerStatsService->aggregateToTournament($event);

            $allUserIds = collect($allStats)->flatMap(function ($players) {
                return array_keys($players);
            })->unique();

            foreach ($allUserIds as $userId) {
                $playerStatsService->aggregateToCareer((int) $userId);
            }
        } catch (\Throwable $e) {
            \Log::warning('Stats aggregation failed: ' . $e->getMessage());
        }

        return $this->redirectToSetup($event, 'Статистика игроков сохранена.');
    }

    /* ================================================================
     *  Тайбрейк: создать матч между двумя командами
     * ================================================================ */

    public function tiebreakerCreateMatch(Request $request, TournamentTiebreaker $tiebreaker)
    {
        $stage = $tiebreaker->stage;
        $event = $stage->event;
        $this->authorizeOrganizer($request, $event);

        if ($tiebreaker->status !== 'pending') {
            return back()->with('error', 'Тайбрейк уже обработан.');
        }

        $maxNumber = TournamentMatch::where('stage_id', $stage->id)->max('match_number') ?? 0;
        $maxRound  = TournamentMatch::where('stage_id', $stage->id)->max('round') ?? 0;

        $match = TournamentMatch::create([
            'stage_id'       => $stage->id,
            'group_id'       => $tiebreaker->group_id,
            'team_home_id'   => $tiebreaker->team_a_id,
            'team_away_id'   => $tiebreaker->team_b_id,
            'round'          => $maxRound + 1,
            'match_number'   => $maxNumber + 1,
            'status'         => TournamentMatch::STATUS_SCHEDULED,
            'is_tiebreaker'  => true,
        ]);

        $tiebreaker->update([
            'method'   => 'match',
            'match_id' => $match->id,
        ]);

        return redirect()
            ->route('tournament.matches.score.form', $match)
            ->with('success', 'Тайбрейк-матч создан. Введите счёт.');
    }

    /* ================================================================
     *  Тайбрейк: жребий — организатор выбирает победителя
     * ================================================================ */

    public function tiebreakerResolveLot(Request $request, TournamentTiebreaker $tiebreaker)
    {
        $stage = $tiebreaker->stage;
        $event = $stage->event;
        $this->authorizeOrganizer($request, $event);

        if ($tiebreaker->status !== 'pending') {
            return back()->with('error', 'Тайбрейк уже обработан.');
        }

        $validated = $request->validate([
            'winner_team_id' => 'required|integer|in:' . $tiebreaker->team_a_id . ',' . $tiebreaker->team_b_id,
        ]);

        $tiebreaker->update([
            'method'              => 'lottery',
            'winner_team_id'      => $validated['winner_team_id'],
            'resolved_by_user_id' => $request->user()->id,
            'resolved_at'         => now(),
            'status'              => 'resolved',
        ]);

        $group = $tiebreaker->group;
        $this->standingsService->recalculateGroup($stage, $group);

        return $this->redirectToSetup($event, 'Жребий проведён, таблица обновлена.');
    }

    /* ================================================================
     *  Tiebreaker SET (2-N команд): Вариант 1 — учесть матчи с аутсайдером
     * ================================================================ */

    public function tiebreakerSetResolveFullDiff(Request $request, TournamentTiebreakerSet $set)
    {
        $stage = $set->stage;
        $event = $stage->event;
        $this->authorizeOrganizer($request, $event);

        if ($set->status !== 'pending') {
            return back()->with('error', 'Этот тайбрейк уже обработан.');
        }

        $teamIds = array_map('intval', $set->team_ids ?? []);
        $standings = TournamentStanding::where('stage_id', $stage->id)
            ->where('group_id', $set->group_id)
            ->whereIn('team_id', $teamIds)
            ->get()
            ->keyBy('team_id');

        // Сортируем по «грязной» разнице (вместе с аутсайдером): rating, points_scored, diff
        $sorted = $teamIds;
        usort($sorted, function ($a, $b) use ($standings) {
            $sa = $standings[$a]; $sb = $standings[$b];
            if ($sa->rating_points !== $sb->rating_points) return $sb->rating_points <=> $sa->rating_points;
            if ($sa->points_scored !== $sb->points_scored) return $sb->points_scored <=> $sa->points_scored;
            $da = $sa->points_scored - $sa->points_conceded;
            $db = $sb->points_scored - $sb->points_conceded;
            return $db <=> $da;
        });

        // Если после сортировки остались равные — это редкий случай, фиксируем как есть.
        $set->update([
            'method'              => 'full_diff',
            'resolved_order'      => $sorted,
            'status'              => 'resolved',
            'resolved_by_user_id' => $request->user()->id,
            'resolved_at'         => now(),
        ]);

        $group = $set->group;
        $this->standingsService->recalculateGroup($stage, $group);

        return $this->redirectToSetup($event, 'Применён расчёт по полной разнице мячей.');
    }

    /* ================================================================
     *  Tiebreaker SET: Вариант 2 — личные встречи (round-robin)
     * ================================================================ */

    public function tiebreakerSetCreateMatches(Request $request, TournamentTiebreakerSet $set)
    {
        $stage = $set->stage;
        $event = $stage->event;
        $this->authorizeOrganizer($request, $event);

        if ($set->status !== 'pending') {
            return back()->with('error', 'Этот тайбрейк уже обработан.');
        }

        $validated = $request->validate([
            'points_to_win'     => 'required|integer|min:1|max:30',
            'two_point_margin'  => 'sometimes|boolean',
        ]);

        $matchSettings = [
            'points_to_win'    => (int) $validated['points_to_win'],
            'two_point_margin' => (bool) ($validated['two_point_margin'] ?? false),
        ];

        $teamIds = array_map('intval', $set->team_ids ?? []);

        DB::transaction(function () use ($stage, $set, $teamIds, $matchSettings) {
            $maxNumber = TournamentMatch::where('stage_id', $stage->id)->max('match_number') ?? 0;
            $maxRound  = TournamentMatch::where('stage_id', $stage->id)->max('round') ?? 0;
            $round = $maxRound + 1;

            // Round-robin между всеми командами связки
            for ($i = 0; $i < count($teamIds); $i++) {
                for ($j = $i + 1; $j < count($teamIds); $j++) {
                    $maxNumber++;
                    TournamentMatch::create([
                        'stage_id'      => $stage->id,
                        'group_id'      => $set->group_id,
                        'team_home_id'  => $teamIds[$i],
                        'team_away_id'  => $teamIds[$j],
                        'round'         => $round,
                        'match_number'  => $maxNumber,
                        'status'        => TournamentMatch::STATUS_SCHEDULED,
                        'is_tiebreaker' => true,
                    ]);
                }
            }

            $set->update([
                'method'         => 'match',
                'match_settings' => $matchSettings,
            ]);
        });

        return $this->redirectToSetup($event, 'Тайбрейк-матчи созданы. Введите их счёт.');
    }

    /* ================================================================
     *  Tiebreaker SET: Вариант 3 — жребий (организатор задаёт порядок)
     * ================================================================ */

    public function tiebreakerSetResolveLottery(Request $request, TournamentTiebreakerSet $set)
    {
        $stage = $set->stage;
        $event = $stage->event;
        $this->authorizeOrganizer($request, $event);

        if ($set->status !== 'pending') {
            return back()->with('error', 'Этот тайбрейк уже обработан.');
        }

        $teamIds = array_map('intval', $set->team_ids ?? []);
        $count = count($teamIds);

        $validated = $request->validate([
            'order'   => 'required|array|size:' . $count,
            'order.*' => 'required|integer|in:' . implode(',', $teamIds),
        ]);

        $order = array_map('intval', $validated['order']);
        if (count(array_unique($order)) !== $count) {
            return back()->with('error', 'Все команды должны быть указаны ровно один раз.');
        }

        $set->update([
            'method'              => 'lottery',
            'resolved_order'      => $order,
            'status'              => 'resolved',
            'resolved_by_user_id' => $request->user()->id,
            'resolved_at'         => now(),
        ]);

        $group = $set->group;
        $this->standingsService->recalculateGroup($stage, $group);

        return $this->redirectToSetup($event, 'Жребий зафиксирован, таблица обновлена.');
    }

    /**
     * Переносит резервные команды лиги в лист ожидания следующего тура.
     * Существующие резервы идут первыми (по reserve_position),
     * только что вылетевшие ($relegatedIds) — в конец.
     */
    private function transferReserveToNextOccurrenceWaitlist(
        \App\Models\Event $event,
        int $currentOccurrenceId,
        array $existingReserveUserIds,
        array $relegatedIds
    ): int {
        if ($currentOccurrenceId <= 0) return 0;

        $currentOcc = \App\Models\EventOccurrence::find($currentOccurrenceId);
        if (!$currentOcc) return 0;

        $nextOcc = \App\Models\EventOccurrence::where('event_id', $event->id)
            ->where('starts_at', '>', $currentOcc->starts_at)
            ->orderBy('starts_at')
            ->first();
        if (!$nextOcc) return 0;

        $maxSort = (int) \Illuminate\Support\Facades\DB::table('occurrence_waitlist')
            ->where('occurrence_id', $nextOcc->id)
            ->max('sort_order');

        $order   = $maxSort + 1;
        $now     = now();
        $inserted = 0;

        // Уже зарегистрированные на следующий тур — не добавляем в waitlist
        $alreadyRegistered = \Illuminate\Support\Facades\DB::table('event_registrations')
            ->where('occurrence_id', $nextOcc->id)
            ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
            ->pluck('user_id')
            ->flip()
            ->toArray();

        // Уже в waitlist следующего тура — не дублируем
        $alreadyInWaitlist = \Illuminate\Support\Facades\DB::table('occurrence_waitlist')
            ->where('occurrence_id', $nextOcc->id)
            ->pluck('user_id')
            ->flip()
            ->toArray();

        $addToWaitlist = function (int $userId) use (
            $nextOcc, &$order, $now, &$inserted,
            $alreadyRegistered, $alreadyInWaitlist
        ) {
            if (isset($alreadyRegistered[$userId]) || isset($alreadyInWaitlist[$userId])) return;
            \Illuminate\Support\Facades\DB::table('occurrence_waitlist')->insert([
                'occurrence_id' => $nextOcc->id,
                'user_id'       => $userId,
                'positions'     => json_encode([]),
                'sort_order'    => $order++,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
            $alreadyInWaitlist[$userId] = true;
            $inserted++;
        };

        // Существующие резервы — в начало
        foreach ($existingReserveUserIds as $userId) {
            if ($userId) $addToWaitlist((int) $userId);
        }

        // Только что вылетевшие — в конец
        foreach (array_unique($relegatedIds) as $userId) {
            if ($userId) $addToWaitlist((int) $userId);
        }

        return $inserted;
    }
}
