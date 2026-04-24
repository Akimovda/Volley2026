<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventTeam;
use App\Models\TournamentStage;
use App\Models\EventTeamApplication;
use App\Models\TournamentGroup;
use App\Models\TournamentMatch;
use App\Models\TournamentStanding;
use App\Services\TournamentSetupService;
use App\Services\TournamentMatchService;
use App\Services\TournamentStandingsService;
use App\Services\TournamentBracketService;
use App\Services\TournamentKingService;
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

        $teams = EventTeam::where('event_id', $event->id)
            ->whereIn('status', ['submitted', 'approved', 'ready'])
            ->with('captain')
            ->get();

        $pendingApplications = EventTeamApplication::where('event_id', $event->id)
            ->where('status', 'pending')
            ->with(['team.captain', 'team.members.user', 'submittedBy'])
            ->get();

        $settings = $event->tournamentSetting;
        $applicationMode = $settings->application_mode ?? 'manual';

        $userEventPhotos = $request->user()->getMedia('event_photos')->sortByDesc('created_at');

        // ── Season / League data ──
        $seasonData = null;
        $selectedOccurrence = null;
        $leagueTeams = collect();

        if ($event->season_id) {
            $season = $event->season()->with('leagues.leagueTeams.team.captain', 'leagues.leagueTeams.user', 'seasonEvents')->first();

            if ($season) {
                $league = $season->leagues->first();
                $occurrences = $event->occurrences()
                    ->whereNull('cancelled_at')
                    ->orderBy('starts_at')
                    ->get();

                $occId = (int) $request->query('occurrence_id', 0);
                $selectedOccurrence = $occId > 0
                    ? $occurrences->firstWhere('id', $occId)
                    : $occurrences->first(); // авто-выбор первого тура

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

        return view('tournaments.setup', compact(
            'event', 'stages', 'teams', 'pendingApplications',
            'applicationMode', 'userEventPhotos',
            'seasonData', 'selectedOccurrence', 'leagueTeams'
        ));
    }

    /* ================================================================
     *  Создать стадию
     * ================================================================ */

    public function createStage(Request $request, Event $event)
    {
        $this->authorizeOrganizer($request, $event);

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
                ->whereIn('status', ['submitted', 'approved', 'ready'])
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

            $payService = app(\App\Services\TournamentPaymentService::class);
            $teams = $teams->filter(fn($t) => $payService->isTeamEligible($t))->values();

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
                    $scheduleService->generateSchedule(
                        $stage,
                        \Carbon\Carbon::parse($scheduleStart),
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

        $teams = EventTeam::where('event_id', $event->id)
            ->whereIn('status', ['submitted', 'approved', 'ready'])
            ->get();

        // Фильтруем неоплаченные команды (если турнир платный)
        $payService = app(\App\Services\TournamentPaymentService::class);
        $eligibleTeams = $teams->filter(fn($t) => $payService->isTeamEligible($t));

        $unpaidCount = $teams->count() - $eligibleTeams->count();
        if ($unpaidCount > 0) {
            \Illuminate\Support\Facades\Log::info("Draw: {$unpaidCount} teams skipped (unpaid)");
        }

        $teams = $eligibleTeams->values();

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
            return back()->with('error', 'Нужно минимум 2 подтверждённых команды.' . ($unpaidCount > 0 ? " ({$unpaidCount} команд не оплатили участие)" : ''));
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

            // Обновляем статистику игроков после матча
            try {
                $freshMatch = $match->fresh();
                $statsService = app(\App\Services\TournamentStatsService::class);
                $statsService->updateAfterMatch($freshMatch);

                // Пересчитываем career stats для игроков обеих команд
                if ($freshMatch->team_home_id) {
                    $homeMembers = \App\Models\EventTeamMember::where('event_team_id', $freshMatch->team_home_id)->pluck('user_id');
                    foreach ($homeMembers as $uid) { $statsService->rebuildCareerStats($uid); }
                }
                if ($freshMatch->team_away_id) {
                    $awayMembers = \App\Models\EventTeamMember::where('event_team_id', $freshMatch->team_away_id)->pluck('user_id');
                    foreach ($awayMembers as $uid) { $statsService->rebuildCareerStats($uid); }
                }
                // Обновляем сезонную статистику
                if ($event->season_id) {
                    app(\App\Services\TournamentSeasonStatsService::class)
                        ->updateForMatch($freshMatch, $event);
                }
            } catch (\Throwable $e) {
                \Log::warning('Stats update failed: ' . $e->getMessage());
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

        $season = $event->season;
        $league = $season->leagues()->first();
        if (!$league) {
            return back()->with('error', 'Лига не найдена.');
        }

        $stages = $event->tournamentStages()->where('name', 'like', 'Группа %')->get();
        $allCompleted = $stages->every(fn($s) => $s->status === 'completed');
        if (!$allCompleted) {
            return back()->with('error', 'Не все группы завершены.');
        }

        $liteStage = $stages->first(fn($s) => str_contains($s->name, 'Lite'));
        $mediumStage = $stages->first(fn($s) => str_contains($s->name, 'Medium'));

        $toReserve = [];

        // Lite: top-2 остаются, остальные в резерв
        if ($liteStage) {
            $standings = \App\Models\TournamentStanding::where('stage_id', $liteStage->id)
                ->orderBy('rank')->get();
            $keepCount = 2;
            foreach ($standings as $s) {
                if ($s->rank > $keepCount) {
                    $toReserve[] = $s->team_id;
                }
            }
        }

        // Medium: top-3 остаются, остальные в резерв
        if ($mediumStage) {
            $standings = \App\Models\TournamentStanding::where('stage_id', $mediumStage->id)
                ->orderBy('rank')->get();
            $keepCount = 3;
            foreach ($standings as $s) {
                if ($s->rank > $keepCount) {
                    $toReserve[] = $s->team_id;
                }
            }
        }

        // Hard: все остаются — ничего не делаем

        $movedCount = 0;
        foreach ($toReserve as $teamId) {
            $lt = \App\Models\TournamentLeagueTeam::where('league_id', $league->id)
                ->where('team_id', $teamId)
                ->where('status', 'active')
                ->first();

            if ($lt) {
                $nextPos = $league->nextReservePosition();
                $lt->update([
                    'status' => 'reserve',
                    'left_at' => now(),
                    'reserve_position' => $nextPos,
                ]);
                $movedCount++;

                // Уведомление о выбывании
                try {
                    $team = $lt->team;
                    if ($team) {
                        app(\App\Services\TournamentNotificationService::class)
                            ->notifyElimination($team, $event, $league->name, $nextPos);
                    }
                } catch (\Throwable $e) {
                    \Log::warning('Promotion notification failed: ' . $e->getMessage());
                }
            }
        }

        // Активируем из резерва если есть
        $vacancies = $movedCount;
        if ($vacancies > 0) {
            $reserves = $league->reserveTeams()
                ->orderBy('reserve_position')
                ->limit($vacancies)
                ->get();

            foreach ($reserves as $rt) {
                // Не активируем тех, кого только что перевели
                if (in_array($rt->team_id, $toReserve)) continue;
                $rt->activateFromReserve();
            }
        }

        return back()->with('success', "Промоушен применён: {$movedCount} команд в резерв.");
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

        $count = $scheduleService->generateSchedule(
            $stage,
            \Carbon\Carbon::parse($validated['start_time']),
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
        abort_unless($application->status === 'pending', 422, 'Заявка уже обработана.');

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
        abort_unless($application->status === 'pending', 422, 'Заявка уже обработана.');

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

        $season = $event->season;
        $league = $season?->leagues()->first();
        if (!$league) {
            return back()->with('error', 'В сезоне нет дивизионов.');
        }

        $teams = EventTeam::where('event_id', $event->id)
            ->whereIn('status', ['submitted', 'approved', 'ready'])
            ->get();

        $added = 0;
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

        if ($added === 0) {
            return back()->with('success', 'Все команды уже в дивизионе.');
        }

        return back()->with('success', "Синхронизация: добавлено {$added} команд в дивизион.");
    }

    private function syncTeamToLeague(Event $event, EventTeam $team): void
    {
        $season = $event->season;
        if (!$season) return;

        $league = $season->leagues()->first();
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
                        ->process($event);
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
}
