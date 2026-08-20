<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventTeam;
use App\Models\KingBeachStanding;
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
use Illuminate\Validation\Rule;
use App\Services\PlayerMatchStatsService;
use App\Models\MatchPlayerStats;
use App\Models\MatchRallyEvent;
use App\Services\MatchRallyService;

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
        private MatchRallyService $rallyService,
    ) {}

    /* ================================================================
     *  Страница настройки турнира (организатор)
     * ================================================================ */

    public function setup(Request $request, Event $event)
    {
        $this->authorizeOrganizer($request, $event);

        $stages = $event->tournamentStages()->with([
            'groups.teams', 'groups.standings', 'groups.standings.team.captain',
            'matches' => fn($q) => $q->orderBy('round')->orderBy('match_number'),
            'matches.teamHome.captain', 'matches.teamAway.captain', 'matches.winner',
        ])->get();

        // ── Season / League data + selectedOccurrence (до загрузки teams!) ──
        $seasonData = null;
        $selectedOccurrence = null;
        $leagueTeams = collect();

        // occurrence_id из query читается для ЛЮБОГО повторяющегося турнира
        // (не только сезонного) — это явное указание пользователя на конкретный
        // тур, приоритетнее любого fallback. Раньше чтение было заперто внутри
        // if(season_id), из-за чего для несезонных турниров параметр молча
        // игнорировался и после старта тура страницу уводило на следующий.
        $occId = (int) $request->query('occurrence_id', 0);
        if ($occId > 0) {
            $selectedOccurrence = $event->occurrences()
                ->whereNull('cancelled_at')
                ->firstWhere('id', $occId);
        }

        if ($event->season_id) {
            $occurrences = $event->occurrences()
                ->whereNull('cancelled_at')
                ->orderBy('starts_at')
                ->get();

            // $selectedOccurrence мог быть уже установлен из occurrence_id выше;
            // если нет (параметра не было) — берём первый occurrence серии.
            if (!$selectedOccurrence) {
                $selectedOccurrence = $occurrences->first();
            }

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

        // Индивидуальная запись: игроки, зарегистрированные на тур, но ещё не попавшие ни в одну команду —
        // нужны для блока "Команды/Игроки" (список + ручное/случайное распределение).
        $unassignedPlayers = collect();
        if (($event->registration_mode ?? '') === 'tournament_individual' && $selectedOccurrence) {
            $assignedUserIds = \App\Models\EventTeamMember::whereHas(
                'team',
                fn($q) => $q->where('event_id', $event->id)->where('occurrence_id', $selectedOccurrence->id)
            )->pluck('user_id');

            $unassignedPlayers = \App\Models\EventRegistration::where('occurrence_id', $selectedOccurrence->id)
                ->whereNull('cancelled_at')
                ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
                ->whereNotIn('user_id', $assignedUserIds)
                ->with('user')
                ->get()
                ->pluck('user')
                ->filter()
                ->values();
        }

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
            'tiebreakerSets', 'cleanStatsByGroup', 'outsidersByGroup',
            'unassignedPlayers'
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
            'kb_group_size'   => ['nullable', 'integer', Rule::in(TournamentKingBeachService::GROUP_SIZES)],
            'finals_mode'     => 'nullable|in:bracket,placement,divisions',
            // advance_per_group больше не отправляется формой — вычисляется ниже
            // (groups_count × advance_count), см. $config.
            // Только для finals_mode='divisions' при groups_count 2/3 (Hard/Lite или
            // Hard/Medium/Lite) — при 4+ группах formDivisions() всё равно не читает
            // per-division ключи вида Medium-N (известный gap), поэтому мастер этот
            // случай не показывает и не собирает вовсе (см. blade).
            'div_format_hard'   => 'nullable|in:bo1,bo3',
            'div_format_medium' => 'nullable|in:bo1,bo3',
            'div_format_lite'   => 'nullable|in:bo1,bo3',
        ]);

        $sortOrder = ($event->tournamentStages()->max('sort_order') ?? 0) + 1;

        $groupsCount = (int) ($validated['groups_count'] ?? 0);

        // Режим финалов после групп: 'placement' (прямые матчи за места, без
        // полуфиналов) доступен только при РОВНО 2 группах — при другом числе
        // групп кросс-посев мест неоднозначен, форсируем 'bracket'. Если
        // организатор явно выбрал 'placement' при groups_count!=2 (например,
        // изменил число групп после выбора радио, JS не успел среагировать) —
        // всё равно форсируем 'bracket' на бэкенде, не доверяя одному JS-гейту.
        // 'divisions' (финальные группы по уровням) такого ограничения не имеет —
        // formDivisions() работает при любом groups_count >= 2, форсировать
        // 'bracket' для него не нужно и не должно (иначе ломается сам смысл выбора).
        $finalsMode = $validated['finals_mode'] ?? ($groupsCount === 2 ? 'placement' : 'bracket');
        if ($finalsMode === 'placement' && $groupsCount !== 2) {
            $finalsMode = 'bracket';
        }

        // Блок кортов (courts_count/courts) общий для групповых форматов и King of the Beach —
        // единое поле "courts" для обоих случаев (advance_count/группы для king_beach
        // задаются позже, на этапе "Сформировать дивизионы").
        $config = [
            'match_format'       => $validated['match_format'],
            'set_points'         => (int) $validated['set_points'],
            'deciding_set_points' => (int) $validated['deciding_set_points'],
            'groups_count'       => $groupsCount,
            'advance_count'      => (int) ($validated['advance_count'] ?? 2),
            'third_place_match'  => (bool) ($validated['third_place_match'] ?? false),
            'courts'             => !empty($validated['courts'])
                ? array_values(array_filter(array_map('trim', explode(',', $validated['courts']))))
                : [],
            'draw_mode'          => $request->input('draw_mode', 'random'),
            'round_number'       => 1,
            'group_size'         => (int) ($validated['kb_group_size'] ?? 4),
            'finals_mode'        => $finalsMode,
            // Только для finals_mode='divisions' — сколько команд из каждой группы
            // проходит в финальные группы по уровням. Больше не редактируемое поле
            // в мастере (было избыточным ручным вводом) — вычисляется тем же
            // способом, что и инфо-строка в setup.blade.php: groups_count × advance_count.
            // Предзаполняет то же поле на пульте (formDivisions()).
            'advance_per_group'  => $finalsMode === 'divisions' ? $groupsCount * (int) ($validated['advance_count'] ?? 2) : null,
            // Дефолт per-division формата матча на пульте (formDivisions()) —
            // overridable там же на день турнира, см. setup.blade.php :2172-2196.
            'div_format_hard'    => $validated['div_format_hard'] ?? null,
            'div_format_medium'  => $validated['div_format_medium'] ?? null,
            'div_format_lite'    => $validated['div_format_lite'] ?? null,
        ];

        // occurrence_id из hidden field (если сезонный турнир)
        $occurrenceId = $request->input('occurrence_id') ?: null;

        // Dedup-guard против двойного сабмита формы (двойной клик / F5 / повторный POST) —
        // баг класса event 402 (два независимых single_elim, оба pending, без взаимной
        // защиты). Критерий скопирован ДОСЛОВНО из quickCreateFinals() (строки ~1624-1627):
        // event_id + occurrence_id + type, БЕЗ sort_order — тот метод тоже не проверяет
        // sort_order, значит и здесь не нужно для консистентности с уже проверенным подходом.
        // Дивизионные под-стадии (Hard/Medium/Lite: division_tier IS NOT NULL ИЛИ имя вида
        // "Группа %") исключены из выборки — они создаются ДРУГИМ путём (formDivisions(),
        // не через этот HTTP-эндпоинт), но исключение оставлено защитно: без него организатор,
        // легитимно создающий НОВУЮ top-level стадию round_robin/groups_playoff, пока в БД уже
        // лежат готовые дивизионные стадии этого же типа от предыдущего этапа турнира, получил
        // бы ложное "уже создана" — та же логика exclusion, что и в dependentStagesWithMatches().
        $existingStage = $event->tournamentStages()
            ->where('type', $validated['type'])
            ->where('occurrence_id', $occurrenceId ? (int) $occurrenceId : null)
            ->where(fn($q) => $q->whereNull('division_tier')->where('name', 'not like', 'Группа %'))
            ->first();
        if ($existingStage) {
            return $this->redirectToSetup(
                $event,
                "Стадия «{$existingStage->name}» этого типа уже создана для этого тура — повторное создание пропущено (защита от двойной отправки формы).",
                true,
                "stage_{$existingStage->id}"
            );
        }

        $stage = $this->setupService->createStage($event, [
            'type'          => $validated['type'],
            'name'          => $validated['name'],
            'sort_order'    => $sortOrder,
            'config'        => $config,
            'occurrence_id' => $occurrenceId ? (int) $occurrenceId : null,
        ]);

        // Кусок 2, шаг 2b: явный скелет финальной стадии при создании турнира.
        // Раньше companion создавался авто и ТОЛЬКО для bracket/placement,
        // divisions пропускался. Теперь сразу создаётся явный скелет ЛЮБОГО из
        // 3 типов финала (выбран в форме, $finalsMode), pending, без матчей —
        // запускается позже кнопкой (launchStage). Баг класса 402 закрыт
        // надёжнее: стадия 1 без стадии 2 не существует ни в один момент.
        // Дубль при двойном сабмите отсекается dedup-guard'ом стадии 1 выше
        // (return ДО этого блока).
        if ($stage->canHaveFollowupStage() && $groupsCount >= 2) {
            if ($finalsMode === 'divisions') {
                // round_robin-скелет БЕЗ groups_count — блок групп ниже
                // (гейт groups_count > 0) его не тронет. launchStage() по нему
                // вызовет formDivisionsCore() из standings стадии 1.
                $this->setupService->createStage($event, [
                    'type'          => TournamentStage::TYPE_ROUND_ROBIN,
                    'name'          => 'Финальные группы',
                    'sort_order'    => $sortOrder + 1,
                    'config'        => [
                        'finals_mode' => 'divisions',
                    ],
                    'occurrence_id' => $occurrenceId ? (int) $occurrenceId : null,
                ]);
            } else {
                // bracket/placement — single_elim-скелет, тем же конфигом
                // (формат/очки/корты/матч за 3-е), finals_mode эагерно.
                $this->setupService->createStage($event, [
                    'type'          => TournamentStage::TYPE_SINGLE_ELIM,
                    'name'          => 'Плей-офф',
                    'sort_order'    => $sortOrder + 1,
                    'config'        => [
                        'match_format'        => $config['match_format'],
                        'set_points'          => $config['set_points'],
                        'deciding_set_points' => $config['deciding_set_points'],
                        'third_place_match'   => $config['third_place_match'],
                        'courts'              => $config['courts'],
                        'finals_mode'         => $finalsMode,
                    ],
                    'occurrence_id' => $occurrenceId ? (int) $occurrenceId : null,
                ]);
            }
        }

        // Для Round Robin / Groups+Playoff — автосоздание групп + жеребьёвка
        if ($stage->canHaveFollowupStage() && $config['groups_count'] > 0) {
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
                    // Ручное распределение: manual_teams[team_id] = ['group' => 'A'|'B'|..., 'position' => int|null]
                    // position необязателен и задаёт порядок посева (seed) внутри группы —
                    // именно этот порядок определяет распределение пар по турам в circle-method.
                    $manualTeams = $request->input('manual_teams', []);
                    $groupLabels = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P'];
                    $groupsByLabel = [];
                    foreach ($groups as $idx => $group) {
                        $label = $groupLabels[$idx] ?? (string)($idx + 1);
                        $groupsByLabel[$label] = $group;
                    }

                    $byLabel = [];
                    foreach ($manualTeams as $teamId => $data) {
                        $label    = is_array($data) ? ($data['group'] ?? null) : $data;
                        $position = is_array($data) ? ($data['position'] ?? null) : null;
                        if (empty($label) || !isset($groupsByLabel[$label])) continue;
                        $byLabel[$label][] = [
                            'team_id'  => (int) $teamId,
                            'position' => ($position !== null && $position !== '') ? (int) $position : null,
                        ];
                    }

                    $assignments = [];
                    foreach ($byLabel as $label => $entries) {
                        // usort стабилен в PHP8: команды без позиции (PHP_INT_MAX) сохраняют
                        // исходный порядок из запроса — это и есть fallback-поведение.
                        usort($entries, fn($a, $b) => ($a['position'] ?? PHP_INT_MAX) <=> ($b['position'] ?? PHP_INT_MAX));
                        $assignments[$groupsByLabel[$label]->id] = array_column($entries, 'team_id');
                    }

                    $this->setupService->drawManual($assignments);
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

        if ($stage->canHaveFollowupStage()) {
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

        } elseif ($stage->isPlayerBasedMatches()) {
            $playerIds = $this->resolveKingBeachPlayers($event, $occurrenceId, $request);
            if (count($playerIds) < 4) {
                return $this->redirectToSetup($event, 'Недостаточно игроков для King of the Beach (минимум 4).', true, "stage_{$stage->id}");
            }
            $this->kingBeachService->createRound($stage, $playerIds);
        }

        return $this->redirectToSetup($event, 'Жеребьёвка проведена, матчи сгенерированы.', false, "stage_{$stage->id}");
    }

    /* ================================================================
     *  King of the Beach — ручное/случайное распределение по группам
     * ================================================================ */

    /**
     * Игроки события/тура, зарегистрированные индивидуально, но ещё
     * не попавшие ни в одну группу данной king_beach стадии.
     */
    private function kingBeachUnassignedIds(Event $event, TournamentStage $stage): array
    {
        $assignedIds = KingBeachStanding::where('stage_id', $stage->id)->pluck('user_id');

        return DB::table('event_registrations')
            ->where('event_id', $event->id)
            ->when($stage->occurrence_id, fn($q) => $q->where('occurrence_id', $stage->occurrence_id))
            ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id')
            ->diff($assignedIds)
            ->map(fn($id) => (int) $id)
            ->values()
            ->toArray();
    }

    public function kingBeachCreateGroup(Request $request, Event $event): RedirectResponse
    {
        $this->authorizeOrganizer($request, $event);

        $validated = $request->validate([
            'stage_id'     => 'required|exists:tournament_stages,id',
            'player_ids'   => 'required|array',
            'player_ids.*' => 'integer|distinct|exists:users,id',
            'court'        => 'nullable|string|max:100',
        ]);

        $stage = TournamentStage::where('id', $validated['stage_id'])
            ->where('event_id', $event->id)
            ->where('type', TournamentStage::TYPE_KING_BEACH)
            ->firstOrFail();

        $groupSize = (int) $stage->configValue('group_size', 4);
        $playerIds = array_map('intval', $validated['player_ids']);

        if (count($playerIds) !== $groupSize) {
            return $this->redirectToSetup($event, "Группа должна содержать ровно {$groupSize} игроков.", true, "stage_{$stage->id}");
        }

        $unassigned = $this->kingBeachUnassignedIds($event, $stage);
        if (count(array_diff($playerIds, $unassigned)) > 0) {
            return $this->redirectToSetup($event, 'Один из выбранных игроков уже распределён в другую группу или не зарегистрирован на этот тур.', true, "stage_{$stage->id}");
        }

        $courts = !empty($validated['court']) ? [$validated['court']] : null;

        try {
            $this->kingBeachService->createManualGroup($stage, $playerIds, $courts);
        } catch (\InvalidArgumentException $e) {
            return $this->redirectToSetup($event, $e->getMessage(), true, "stage_{$stage->id}");
        }

        return $this->redirectToSetup($event, 'Группа создана.', false, "stage_{$stage->id}");
    }

    public function kingBeachDistributeRandom(Request $request, Event $event): RedirectResponse
    {
        $this->authorizeOrganizer($request, $event);

        $validated = $request->validate([
            'stage_id' => 'required|exists:tournament_stages,id',
        ]);

        $stage = TournamentStage::where('id', $validated['stage_id'])
            ->where('event_id', $event->id)
            ->where('type', TournamentStage::TYPE_KING_BEACH)
            ->firstOrFail();

        $playerIds = $this->kingBeachUnassignedIds($event, $stage);
        $groupSize = (int) $stage->configValue('group_size', 4);

        if (count($playerIds) < $groupSize) {
            return $this->redirectToSetup($event, "Недостаточно нераспределённых игроков (минимум {$groupSize}).", true, "stage_{$stage->id}");
        }

        $result = $this->kingBeachService->distributeIntoGroups($stage, $playerIds);

        $message = 'Сформировано групп: ' . count($result['groups']) . '.';
        if (!empty($result['leftover'])) {
            $message .= ' Не хватило до полной группы (осталось нераспределённых): ' . count($result['leftover']) . '.';
        }

        return $this->redirectToSetup($event, $message, false, "stage_{$stage->id}");
    }

    /**
     * Ручное распределение нескольких игроков сразу через одну таблицу:
     * assign[user_id] = 'произвольный ярлык группы' (например A, B, Hard...).
     * Каждый непустой ярлык должен собрать РОВНО group_size игроков (4 или 6 —
     * см. configValue('group_size', 4) стадии).
     */
    public function kingBeachAssignManual(Request $request, Event $event): RedirectResponse
    {
        $this->authorizeOrganizer($request, $event);

        $validated = $request->validate([
            'stage_id'  => 'required|exists:tournament_stages,id',
            'assign'    => 'required|array',
            'assign.*'  => 'nullable|string|max:20',
        ]);

        $stage = TournamentStage::where('id', $validated['stage_id'])
            ->where('event_id', $event->id)
            ->where('type', TournamentStage::TYPE_KING_BEACH)
            ->firstOrFail();

        $unassigned = $this->kingBeachUnassignedIds($event, $stage);
        $groupSize = (int) $stage->configValue('group_size', 4);

        $buckets = [];
        foreach ($validated['assign'] as $userId => $label) {
            $label = trim((string) $label);
            $userId = (int) $userId;

            if ($label === '' || !in_array($userId, $unassigned, true)) {
                continue;
            }

            $buckets[$label][] = $userId;
        }

        if (empty($buckets)) {
            return $this->redirectToSetup($event, 'Не выбрано ни одного игрока для распределения.', true, "stage_{$stage->id}");
        }

        $wrongCount = array_keys(array_filter($buckets, fn($ids) => count($ids) !== $groupSize));
        if (!empty($wrongCount)) {
            return $this->redirectToSetup(
                $event,
                "В каждой группе должно быть ровно {$groupSize} игроков. Проверьте группы: " . implode(', ', $wrongCount),
                true,
                "stage_{$stage->id}"
            );
        }

        $created = 0;
        foreach ($buckets as $label => $ids) {
            $this->kingBeachService->createManualGroup($stage, $ids, null, $label);
            $created++;
        }

        return $this->redirectToSetup($event, "Группы созданы: {$created}.", false, "stage_{$stage->id}");
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
        if ($stage->isPlayerBasedMatches()) {
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

            // Standings ЗАТРОНУТОЙ этим матчем группы уже пересчитаны синхронно
            // внутри recordScore()/submitScore() (быстро, одна группа) — организатор
            // видит верную таблицу без задержки. "Тяжёлый хвост" — player_tournament_stats
            // всего события + career + (для сезонных) весь сезон — в очередь, не
            // блокирует ответ страницы (report_league_tournament_setup_diag_2026-08-07.md).
            try {
                \App\Jobs\RecalculateTournamentStatsJob::dispatch($event->id)->afterCommit();
            } catch (\Throwable $e) {
                \Log::warning('Stats rebuild dispatch failed: ' . $e->getMessage());
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
        if ($stage->isPlayerBasedMatches()) {
            // Сбрасываем статус чтобы scoreKingBeach мог принять (он проверяет только данные)
            $match->update(['status' => TournamentMatch::STATUS_SCHEDULED]);
            return $this->scoreKingBeach($request, $match, $stage);
        }

        if (!$match->isCompleted()) {
            return back()->with('error', 'Матч не завершён — используйте обычный ввод счёта.');
        }

        $stageIsDivStage = $stage->division_tier !== null || str_starts_with($stage->name, 'Группа ');
        if (!$stageIsDivStage) {
            $hasDivStages = $event->tournamentStages()
                ->where('occurrence_id', $stage->occurrence_id)
                ->where(fn($q) => $q->whereNotNull('division_tier')
                    ->orWhere('name', 'like', 'Группа %'))
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

            // См. комментарий в score() — standings группы уже пересчитаны синхронно
            // внутри resetScore()/recordScore(), тяжёлый пересчёт события/сезона — в очередь.
            try {
                \App\Jobs\RecalculateTournamentStatsJob::dispatch($event->id)->afterCommit();
            } catch (\Throwable $e) {
                \Log::warning('Stats rebuild dispatch after rescore failed: ' . $e->getMessage());
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

        $hasRallyData = !$match->isCompleted()
            && !$stage->isPlayerBasedMatches()
            && MatchRallyEvent::where('match_id', $match->id)->exists();

        $canReopenViaRally = $match->isCompleted()
            && !$stage->isPlayerBasedMatches()
            && $match->hasTeams();

        return view('tournaments.score', compact('event', 'match', 'stage', 'hasRallyData', 'canReopenViaRally'));
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

        // Стадии текущего тура (Hard/Lite/Medium группы). division_tier — основной
        // признак, паттерн по имени — фоллбэк для стадий без backfill (см. план миграции).
        $stagesQuery = $event->tournamentStages()
            ->where(fn($q) => $q->whereNotNull('division_tier')
                ->orWhere(fn($q2) => $q2->where('name', 'like', 'Группа %')
                    ->where('name', '!=', 'Групповой этап')));
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

        // division_tier — основной признак "самый слабый дивизион (Lite)" / "средний
        // (Medium)": максимальный tier в наборе = Lite, всё между Hard(=1) и Lite = Medium.
        // Фоллбэк на текстовый паттерн — только для стадий без проставленного tier.
        $tiersPresent = $stages->pluck('division_tier')->filter(fn($t) => $t !== null);
        $maxTier = $tiersPresent->isNotEmpty() ? $tiersPresent->max() : null;

        $isLiteStage = fn($s) => $s->division_tier !== null
            ? $s->division_tier === $maxTier
            : str_contains($s->name, 'Lite');
        $isMediumStage = fn($s) => $s->division_tier !== null
            ? ($s->division_tier > 1 && $s->division_tier !== $maxTier)
            : str_contains($s->name, 'Medium');

        foreach ($stages->filter($isLiteStage) as $stage) {
            foreach (\App\Models\TournamentStanding::where('stage_id', $stage->id)->orderBy('rank')->get() as $s) {
                if ($s->rank > 2) $collectRelegated($s->team_id);
            }
        }
        foreach ($stages->filter($isMediumStage) as $stage) {
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

        try {
            $divisionNames = $this->formDivisionsCore($event, $stage, $request);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return $this->redirectToSetup($event, 'Группы сформированы: ' . implode(', ', $divisionNames), false, 'promotion_block');
    }

    /**
     * Кусок 2, шаг 2а (2026-08-15) — извлечено дословно из formDivisions() без
     * изменения алгоритма, чтобы переиспользовать в НОВОМ launchStage()
     * (divisions-ветка) для скелет-стадий, не дублируя ~150 строк
     * распределения по дивизионам. $stage здесь — ТА ЖЕ завершённая групповая
     * стадия, что и раньше (route-параметр formDivisions() или $prevStage из
     * launchStage()) — advance_per_group/div_format_* по-прежнему читаются из
     * ЕЁ конфига (см. комментарии ниже), а не из скелета: скелет divisions не
     * дублирует эти поля, они и так лежат на групповой стадии с момента её
     * создания (createStage(), config.advance_per_group/div_format_*).
     *
     * @return string[]  Названия сформированных дивизионов (Hard/Medium/Lite/...)
     * @throws \InvalidArgumentException  Если группы ещё не сыграны (<2 групп)
     */
    protected function formDivisionsCore(Event $event, TournamentStage $stage, Request $request): array
    {
        $groups = $stage->groups()->with(['standings' => fn($q) => $q->orderBy('rank')])->get();
        $groupsCount = $groups->count();

        if ($groupsCount < 2) {
            throw new \InvalidArgumentException('Нужно минимум 2 группы для распределения.');
        }

        // Определяем названия дивизионов
        $divisionNames = TournamentStage::divisionNamesFor($groupsCount);

        // Собираем standings по рангам — храним сами объекты TournamentStanding
        // (не расплющенные массивы), чтобы сортировать единым критерием
        // compareStrength() (Кусок 3 — тот же "кто сильнее", что и в
        // TournamentBracketService::advanceToPlayoff() для добора в плей-офф),
        // а не дублировать формулу очки→сеты→мячи вручную второй раз.
        $byRank = []; // rank => [TournamentStanding, ...]
        foreach ($groups as $group) {
            foreach ($group->standings->sortBy('rank') as $standing) {
                $byRank[$standing->rank][] = $standing;
            }
        }

        // Сортируем внутри каждого ранга единым критерием "кто сильнее"
        foreach ($byRank as $rank => &$teams) {
            usort($teams, fn($a, $b) => $this->standingsService->compareStrength($a, $b));
        }
        unset($teams);

        // Команды по дивизионам — ключ ВСЕГДА точное имя дивизиона (Hard, Medium,
        // Medium-1, Medium-2, ..., Lite), не свёрнутое "Hard/Medium/Lite" на троих.
        // До фикса при 4+ группах все "Medium-N" дивизионы схлопывались в ОДИН
        // общий пул $mediumTeamIds, и каждый из них (через explode('-',...)[0]
        // фоллбэк ниже) получал ПОЛНУЮ объединённую копию — команды дублировались
        // сразу в нескольких финальных группах одновременно. См. CLAUDE.md
        // (раздел «Турниры» — баг Medium-N в formDivisions()).
        $teamsByDivision = array_fill_keys($divisionNames, []);

        // Единый алгоритм раскладки (модель A — ровные размеры дивизионов).
        // $byRank уже отсортирован внутри каждого ранга через compareStrength()
        // выше, поэтому плоский список по рангам идёт строго по силе, и нарезка
        // array_slice подряд = добор лучших в старшие дивизионы (та же механика
        // "кто сильнее", что и добор в плей-офф, но цель — целевой размер
        // дивизиона, а не степень двойки). Остаток от неровного деления идёт
        // в старшие дивизионы (вариант B: 7 команд на 3 → 3/2/2).
        $allTeamsByQuality = [];
        ksort($byRank); // ранги строго по возрастанию: 1, 2, 3...
        foreach ($byRank as $teams) {
            foreach ($teams as $t) {
                $allTeamsByQuality[] = $t; // TournamentStanding, уже отсортирован по силе
            }
        }

        $totalTeams    = count($allTeamsByQuality);
        $divisionCount = count($divisionNames);
        $base          = $divisionCount > 0 ? intdiv($totalTeams, $divisionCount) : 0;
        $remainder     = $divisionCount > 0 ? $totalTeams % $divisionCount : 0;

        $offset = 0;
        foreach ($divisionNames as $idx => $name) {
            $size = $idx < $remainder ? $base + 1 : $base;
            $teamsByDivision[$name] = array_column(
                array_slice($allTeamsByQuality, $offset, $size),
                'team_id'
            );
            $offset += $size;
        }

        // occurrence_id из текущей стадии или из query
        $occurrenceId = $stage->occurrence_id;

        // Форматы матчей для групп — поле убрано с пульта (аналогично
        // advance_per_group), значение всегда из конфига стадии, заданного в
        // мастере. $request->input() остаётся приоритетным на случай прямого
        // API-вызова с явным параметром (форма его больше не отправляет).
        // Ключ — точное имя дивизиона (div_format_medium-1, div_format_medium-2,
        // ...), не свёрнутое "medium" на всех — иначе при 4+ группах формат для
        // Medium-N никогда не читался (только 3 фикс-ключа hard/medium/lite).
        $divFormats = [];
        foreach ($divisionNames as $dn) {
            $key = strtolower($dn);
            $divFormats[$dn] = $request->input('div_format_' . $key) ?: $stage->cfg('div_format_' . $key);
        }

        $setupService = app(\App\Services\TournamentSetupService::class);

        \Illuminate\Support\Facades\DB::transaction(function () use (
            $event, $setupService, $divisionNames, $teamsByDivision, $stage, $occurrenceId, $divFormats, $request
        ) {
            // Удаляем ранее созданные дивизионные стадии (Hard/Medium/Lite) перед пересозданием.
            // division_tier — основной признак; паттерн по имени остаётся фоллбэком для
            // стадий, созданных до появления этого поля (см. report_division_tier_migration_plan_2026-08-04.md).
            $existing = $event->tournamentStages()
                ->where('occurrence_id', $stage->occurrence_id)
                ->where(fn($q) => $q->whereNotNull('division_tier')
                    ->orWhere(fn($q2) => $q2->where('name', 'like', 'Группа %')
                        ->where('name', '!=', 'Групповой этап')))
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

            $sortOrder = ($event->tournamentStages()->max('sort_order') ?? 0) + 1;

            foreach ($divisionNames as $divIndex => $divName) {
                $teamIds = $teamsByDivision[$divName] ?? [];
                if (empty($teamIds)) continue;

                // Создаём стадию-группу (Round Robin внутри)
                $divStage = $setupService->createStage($event, [
                    'type'          => 'round_robin',
                    'name'          => 'Группа ' . $divName,
                    // 1 = самый сильный (Hard) — позиция в $divisionNames, не текст имени.
                    'division_tier' => $divIndex + 1,
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

        // Повторное формирование дивизионов удаляет ранее созданные "Группа Hard/Lite"
        // (строки выше, "Удаляем ранее созданные дивизионные стадии перед пересозданием")
        // — если у них уже были сыгранные матчи, их вклад в player_tournament_stats/
        // OpenSkill/career оставался фантомом без пересчёта (найдено при аудите
        // recalculateTournament(), report_league_tournament_setup_diag_2026-08-07.md).
        // При первом формировании (ничего не удалялось) — просто безвредный no-op
        // пересчёт того, что уже верно.
        try {
            \App\Jobs\RecalculateTournamentStatsJob::dispatch($event->id)->afterCommit();
        } catch (\Throwable $e) {
            \Log::warning('Stats rebuild dispatch after formDivisions failed: ' . $e->getMessage());
        }

        return $divisionNames;
    }

    /**
     * King of the Beach: после завершения группового этапа — распределить ВСЕХ
     * игроков по новым дивизионам Hard/Medium/Lite (аналог formDivisions(), но
     * по индивидуальным standings; работает и вне сезонных турниров).
     */
    public function kingBeachFormDivisions(Request $request, TournamentStage $stage): RedirectResponse
    {
        $event = $stage->event;
        $this->authorizeOrganizer($request, $event);

        if (!$stage->isCompleted()) {
            return back()->with('error', 'Стадия ещё не завершена.');
        }

        $advancePerGroup = max(1, (int) $request->input(
            'advance_per_group',
            $stage->configValue('advance_count', 2)
        ));

        try {
            $divisions = $this->kingBeachService->formDivisions($stage, $advancePerGroup);
        } catch (\InvalidArgumentException $e) {
            return $this->redirectToSetup($event, $e->getMessage(), true, "stage_{$stage->id}");
        }

        return $this->redirectToSetup(
            $event,
            'Дивизионы сформированы: ' . implode(', ', array_keys($divisions)),
            false,
            "stage_{$stage->id}"
        );
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

    /**
     * "Прямые матчи по местам" — альтернатива advance() для турниров ровно
     * с 2 группами: 1-е места групп играют за 1-2 место, 2-е — за 3-4,
     * без дополнительного раунда полуфиналов между группами (см. докстринг
     * TournamentBracketService::generateGroupCrossover()).
     */
    public function advanceCrossover(Request $request, TournamentStage $stage)
    {
        $event = $stage->event;
        $this->authorizeOrganizer($request, $event);

        if (! $stage->isCompleted()) {
            return back()->with('error', 'Групповая стадия ещё не завершена.');
        }

        $validated = $request->validate([
            'playoff_stage_id' => 'required|exists:tournament_stages,id',
            'places_count' => 'required|integer|in:2,4,6,8',
        ]);

        $playoffStage = TournamentStage::where('id', $validated['playoff_stage_id'])
            ->where('event_id', $event->id)
            ->firstOrFail();

        try {
            $created = $this->bracketService->generateGroupCrossover(
                $stage, $playoffStage,
                (int) $validated['places_count'],
                $this->standingsService,
            );

            if ($created->isEmpty()) {
                return $this->redirectToSetup($event, 'Все запрошенные места уже разыграны — новых матчей не создано.');
            }

            // Стадия могла уже быть completed (например, дозаполнение матча
            // за 3-4 после того, как "за 1-2" уже сыгран) — возвращаем её в
            // активное состояние, чтобы новый матч можно было ввести штатно.
            // Название переименовываем в "Финал" только если оно ещё дефолтное
            // ("Плей-офф", проставленное при автосоздании парной стадии) — не
            // затираем имя, если организатор уже переименовал стадию сам.
            $updateData = ['status' => TournamentStage::STATUS_IN_PROGRESS];
            if ($playoffStage->name === 'Плей-офф') {
                $updateData['name'] = 'Финал';
            }
            $playoffStage->update($updateData);

            return $this->redirectToSetup($event, 'Матчи по местам созданы.');

        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Кусок 2, шаг 2а (2026-08-15) — единая точка «запустить стадию 2+» для
     * НОВОЙ модели «скелет → запуск» (не заменяет старые advance()/
     * advanceCrossover()/formDivisions() — те продолжают обслуживать
     * companion-стадии, созданные ДО этого рефакторинга, см. CLAUDE.md).
     *
     * $stage — pending-скелет (single_elim с config.finals_mode=bracket|
     * placement, ЛИБО round_robin-скелет с config.finals_mode=divisions,
     * см. новый опциональный блок в createStage()). Предыдущая стадия
     * ищется автоматически (тот же occurrence, ближайший меньший
     * sort_order) — в отличие от advance()/advanceCrossover(), где
     * наоборот $stage=групповая, а playoff-стадия передаётся явно
     * (playoff_stage_id) — здесь URL уже указывает на конкретный скелет,
     * второй ID в запросе не нужен.
     */
    public function launchStage(Request $request, TournamentStage $stage)
    {
        $event = $stage->event;
        $this->authorizeOrganizer($request, $event);

        if (!$stage->isPending()) {
            return back()->with('error', 'Эта стадия уже запущена или завершена.');
        }

        $prevStage = $event->tournamentStages()
            ->where('occurrence_id', $stage->occurrence_id)
            ->where('sort_order', '<', $stage->sort_order)
            ->orderByDesc('sort_order')
            ->first();

        // Кусок 2, шаг 2b: старые companion-стадии (созданы до 2a) не имеют
        // finals_mode на себе — он лежит на родительской групповой стадии.
        // Фолбэк на $prevStage сохраняет запуск для таких турниров (event 549).
        $finalsMode = $stage->cfg('finals_mode') ?? $prevStage?->cfg('finals_mode');
        if (!in_array($finalsMode, ['bracket', 'placement', 'divisions'], true)) {
            return back()->with('error', 'У стадии не задан тип финала (finals_mode) — запуск невозможен.');
        }

        if (!$prevStage || !$prevStage->isCompleted()) {
            return back()->with('error', 'Предыдущая стадия ещё не завершена.');
        }

        // Паритет со старыми advance()/advanceCrossover() — там эти параметры были
        // required с тем же диапазоном; здесь nullable, т.к. launchStage()/
        // TournamentSetupService умеет фолбэчиться на cfg('advance_count')/дефолт 2.
        if ($finalsMode === 'bracket') {
            $request->validate(['advance_per_group' => 'nullable|integer|min:1|max:8']);
        } elseif ($finalsMode === 'placement') {
            $request->validate(['places_count' => 'nullable|integer|in:2,4,6,8']);
        }

        try {
            if ($finalsMode === 'divisions') {
                $divisionNames = $this->formDivisionsCore($event, $prevStage, $request);
                // Скелет своей роли (носитель config.finals_mode=divisions до
                // запуска) больше не несёт — реальные "Группа X" стадии уже
                // созданы formDivisionsCore(). Помечаем completed (не удаляем —
                // см. report/kusok2_shag2_plan_2026-08-15.md §1.6, "конкретика
                // на усмотрение реализации 2a"), чтобы он не путался с
                // pending-стадиями на пульте и не попадал в batch-подсчёт
                // checkStageCompletion() как незавершённый.
                $stage->update(['status' => TournamentStage::STATUS_COMPLETED]);
                $message = 'Группы сформированы: ' . implode(', ', $divisionNames);
            } else {
                $result = $this->setupService->launchStage($stage, $prevStage, $request->all());
                $message = $result['message'];
            }
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return $this->redirectToSetup($event, $message, false, "stage_{$stage->id}");
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

            } elseif ($stage->isPlayerBasedMatches()) {
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
     * Быстрое создание финальной стадии одним кликом — для случая, когда
     * организатор удалил единственную single_elim стадию (revert/delete,
     * см. инцидент event 402) и застрял без штатного способа создать её
     * заново, кроме ручного заполнения формы "Добавить стадию". Создаёт
     * стадию с параметрами групповой (формат/очки/корты/матч за 3-е место)
     * и сразу генерирует финалы по finals_mode групповой стадии.
     */
    public function quickCreateFinals(Request $request, TournamentStage $stage)
    {
        $event = $stage->event;
        $this->authorizeOrganizer($request, $event);

        if (!$stage->canHaveFollowupStage() || !$stage->isCompleted()) {
            return back()->with('error', 'Групповая стадия должна быть завершена.');
        }

        // Турнир одного типа "группы+плей-офф" может иметь только ОДНУ финальную
        // стадию — либо сетку, либо прямые матчи за места, но не обе сразу
        // (см. report_402_finals_bug.md, дубль стадий 107/108 у event 402).
        $existingPlayoff = $event->tournamentStages()
            ->where('type', TournamentStage::TYPE_SINGLE_ELIM)
            ->where('occurrence_id', $stage->occurrence_id)
            ->first();
        if ($existingPlayoff) {
            return $this->redirectToSetup(
                $event,
                "Финальная стадия «{$existingPlayoff->name}» уже создана — используйте блок «Сгенерировать финалы» ниже, а не повторное создание.",
                true,
                "stage_{$existingPlayoff->id}"
            );
        }

        $isTwoGroups = $stage->groups->count() === 2;
        $finalsMode = $stage->cfg('finals_mode', $isTwoGroups ? 'placement' : 'bracket');
        if (!$isTwoGroups) {
            $finalsMode = 'bracket';
        }

        $sortOrder = ($event->tournamentStages()->max('sort_order') ?? 0) + 1;
        $playoffStage = $this->setupService->createStage($event, [
            'type'          => TournamentStage::TYPE_SINGLE_ELIM,
            'name'          => $finalsMode === 'placement' ? 'Финал' : 'Плей-офф',
            'sort_order'    => $sortOrder,
            'config'        => [
                'match_format'        => $stage->cfg('match_format', 'bo3'),
                'set_points'          => $stage->cfg('set_points', 25),
                'deciding_set_points' => $stage->cfg('deciding_set_points', 15),
                'third_place_match'   => $stage->cfg('third_place_match', false),
                'courts'              => $stage->cfg('courts', []),
            ],
            'occurrence_id' => $stage->occurrence_id,
        ]);

        try {
            if ($finalsMode === 'placement') {
                $placesCount = $stage->groups->count() * 2;
                $this->bracketService->generateGroupCrossover($stage, $playoffStage, $placesCount, $this->standingsService);
            } else {
                $advancePerGroup = (int) $stage->cfg('advance_count', 2);
                $this->bracketService->advanceToPlayoff($stage, $playoffStage, $advancePerGroup, $this->standingsService);
            }
            $playoffStage->update(['status' => TournamentStage::STATUS_IN_PROGRESS]);
        } catch (\InvalidArgumentException $e) {
            return $this->redirectToSetup($event, $e->getMessage(), true, "stage_{$playoffStage->id}");
        }

        return $this->redirectToSetup($event, 'Финальная стадия создана, матчи готовы к вводу счёта.', false, "stage_{$playoffStage->id}");
    }


    /**
     * Откат стадии — сброс всех матчей и standings с сохранением структуры.
     */
    public function revertStage(Request $request, TournamentStage $stage)
    {
        $event = $stage->event;
        $this->authorizeOrganizer($request, $event);

        $blocking = $this->dependentStagesWithMatches($stage);
        if ($blocking->isNotEmpty()) {
            $names = $blocking->pluck('name')->implode(', ');
            return $this->redirectToSetup(
                $event,
                "Сначала откатите следующую стадию: {$names} — у неё уже есть сыгранные матчи.",
                true,
                "stage_{$stage->id}"
            );
        }

        // King of the Beach: полный сброс (группы + матчи + standings)
        if ($stage->isPlayerBasedMatches()) {
            $this->kingBeachService->revertStage($stage);
            return $this->redirectToSetup($stage->event, 'Стадия King of the Beach сброшена.', false, "stage_{$stage->id}");
        }

        $resetCount = 0;
        DB::transaction(function () use ($stage, &$resetCount) {
            // Удаляем связанные группы Hard/Lite (если это групповой этап).
            // division_tier — основной признак, паттерн по имени — фоллбэк.
            if ($stage->canHaveFollowupStage()) {
                $divQuery = $stage->event->tournamentStages()
                    ->where('id', '!=', $stage->id)
                    ->where(fn($q) => $q->whereNotNull('division_tier')
                        ->orWhere('name', 'like', 'Группа %'));
                if ($stage->occurrence_id) {
                    $divQuery->where('occurrence_id', $stage->occurrence_id);
                }
                $divQuery->get()->each(fn($ds) => $ds->delete());
            }

            // Сбрасываем все матчи
            $resetCount = $stage->matches()->update([
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
            if ($stage->isBracketStage()) {
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

        // См. аналогичный сброс в destroyStage() (баг №3, 2026-08-15) — откат
        // стадии тоже может лишить турнир завершённости, claim-флаг уведомления
        // нужно снять, чтобы оно ушло заново при повторном реальном завершении.
        DB::table('events')->where('id', $event->id)->update(['tournament_completed_notified_at' => null]);

        // Откат стадии раньше не запускал никакого пересчёта вообще — переигранный
        // после отката матч не был бы учтён ни в Elo, ни в OpenSkill, ни в standings
        // события за пределами этой стадии (см. report_recalc_system_diagnosis).
        // В очередь, не синхронно — та же причина, что и в score()/rescoreMatch().
        try {
            \App\Jobs\RecalculateTournamentStatsJob::dispatch($event->id)->afterCommit();
        } catch (\Throwable $e) {
            \Log::warning('Stats rebuild dispatch after stage revert failed: ' . $e->getMessage());
        }

        $matchesWord = trans_choice('матч|матча|матчей', $resetCount);
        return $this->redirectToSetup(
            $event,
            "Стадия \"{$stage->name}\" откачена — сброшены счета {$resetCount} {$matchesWord}, стадия снова активна.",
            false,
            "stage_{$stage->id}"
        );
    }

        public function destroyStage(Request $request, TournamentStage $stage)
    {
        $event = $stage->event;
        $this->authorizeOrganizer($request, $event);

        $blocking = $this->dependentStagesWithMatches($stage);
        if ($blocking->isNotEmpty()) {
            $names = $blocking->pluck('name')->implode(', ');
            return $this->redirectToSetup(
                $event,
                "Сначала откатите/удалите следующую стадию: {$names} — у неё уже есть сыгранные матчи.",
                true,
                "stage_{$stage->id}"
            );
        }

        $name = $stage->name;
        $divNames = '';
        $deletedStageId = $stage->id;
        $occurrenceId = $stage->occurrence_id;

        // Если удаляем групповой этап — удалить и связанные группы Hard/Lite.
        // division_tier — основной признак, паттерн по имени — фоллбэк.
        if ($stage->canHaveFollowupStage()) {
            $divStages = $event->tournamentStages()
                ->where('id', '!=', $stage->id)
                ->where(function($q) {
                    $q->whereNotNull('division_tier')
                        ->orWhere('name', 'like', 'Группа %');
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

        // Удаление стадии может лишить турнир завершённости (баг №3, 2026-08-15):
        // если turnament_completed_notified_at уже был проставлен (уведомление
        // "Турнир завершён!" уже ушло), а мы только что удалили стадию, из-за
        // которой турнир считался полностью сыгранным — снимаем claim-флаг, чтобы
        // при повторном реальном завершении (после пересоздания и доигровки
        // удалённой стадии) уведомление отправилось ещё раз, а не молчало навсегда.
        // Безвредно, если флаг и так был NULL.
        DB::table('events')->where('id', $event->id)->update(['tournament_completed_notified_at' => null]);

        // Якорь для редиректа: если после удаления осталась групповая стадия
        // (обычно так и есть — удаляют именно плей-офф, группа жива) —
        // приземляем организатора на её карточку, а не в начало страницы
        // (~2700 строк setup.blade.php). Если групповой стадии тоже нет —
        // anchor=null, редирект наверх списка стадий.
        $anchorStage = $event->tournamentStages()
            ->where('id', '!=', $deletedStageId)
            ->whereIn('type', ['round_robin', 'groups_playoff'])
            ->when($occurrenceId, fn($q) => $q->where('occurrence_id', $occurrenceId))
            ->orderBy('sort_order')
            ->first();
        $anchor = $anchorStage ? "stage_{$anchorStage->id}" : null;

        // Удаление стадии с уже завершёнными матчами раньше не запускало никакого
        // пересчёта — их вклад в Elo/OpenSkill/player_tournament_stats оставался
        // навсегда, хотя сами результаты уже удалены (найдено при аудите системы
        // пересчёта, см. report_recalc_implementation_plan). В очередь, не синхронно.
        try {
            \App\Jobs\RecalculateTournamentStatsJob::dispatch($event->id)->afterCommit();
        } catch (\Throwable $e) {
            \Log::warning('Stats rebuild dispatch after stage delete failed: ' . $e->getMessage());
        }

        $msg = "Стадия \"{$name}\" удалена.";
        if (!empty($divNames)) {
            $msg .= " Также удалены: {$divNames}.";
        }

        return $this->redirectToSetup($event, $msg, false, $anchor);
    }

    /**
     * Стадии того же турнира (и того же occurrence_id, если он задан) с
     * sort_order строго больше, чем у $stage, у которых уже есть матчи —
     * т.е. реально "запущенные" следующие стадии, зависящие от посева этой.
     *
     * Дивизионные под-стадии (Hard/Medium/Lite: division_tier IS NOT NULL
     * ИЛИ имя вида "Группа %") исключаются — они не "следующая стадия", а
     * ПОРОЖДЕНИЕ текущей: у них sort_order тоже больше (создаются через
     * max(sort_order)+1 после родителя, см. formDivisions() ~1347-1359),
     * но revertStage()/destroyStage() уже удаляют/сбрасывают их вместе с
     * родителем в canHaveFollowupStage()-ветке выше по файлу — сами по себе
     * они не блокирующая зависимость, а часть той же операции отката/удаления.
     */
    private function dependentStagesWithMatches(TournamentStage $stage): \Illuminate\Support\Collection
    {
        $query = $stage->event->tournamentStages()
            ->where('id', '!=', $stage->id)
            ->where('sort_order', '>', $stage->sort_order)
            ->where(fn($q) => $q->whereNull('division_tier')
                ->where('name', 'not like', 'Группа %'));

        if ($stage->occurrence_id) {
            $query->where('occurrence_id', $stage->occurrence_id);
        }

        return $query->get()->filter(fn($s) => $s->matches()->exists())->values();
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

        // Ручная синхронизация — помечаем тур как синхронизированный (блокирует авто-job)
        if ($occurrenceId > 0 && $seasonEvt) {
            $seasonEvt->update(['synced_at' => now()]);
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

        // Владелец ИЛИ staff у владельца — роль organizer не отменяет
        // staff-назначение на чужих мероприятиях.
        if (!app(\App\Services\EventAccessService::class)->canManageEvent($user, (int) $event->organizer_id)) {
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

            // === АВТОЗАПУСК bracket-плей-офф (только несезонные туры) ===
            // Сезонные туры сюда не доходят (early return выше) — у них запуск ручной.
            // Для несезонных после завершения группового этапа автоматически
            // запускаем pending-скелет с finals_mode='bracket'. Placement и divisions
            // остаются ручными. Защита от гонки: два последних матча группы могут
            // завершиться почти одновременно (параллельные корты, два запроса) — оба
            // дойдут сюда, а advanceToPlayoff() неатомарен (delete→create), что дало
            // бы дубли сетки. Атомарный claim pending→in_progress одним UPDATE
            // пропускает ровно один параллельный запрос (affected=1), остальные видят
            // affected=0 и просто ничего не делают. launchStage обёрнут в транзакцию
            // (advanceToPlayoff сам неатомарен) — при сбое откатывается, claim
            // возвращается в pending, чтобы организатор мог запустить вручную.
            $autoNext = $event->tournamentStages()
                ->where('occurrence_id', $stage->occurrence_id)
                ->where('sort_order', '>', $stage->sort_order)
                ->where('status', TournamentStage::STATUS_PENDING)
                ->orderBy('sort_order')
                ->first();

            if ($autoNext && $autoNext->cfg('finals_mode') === 'bracket') {
                $claimed = TournamentStage::where('id', $autoNext->id)
                    ->where('status', TournamentStage::STATUS_PENDING)
                    ->update(['status' => TournamentStage::STATUS_IN_PROGRESS]);

                if ($claimed === 1) {
                    $autoPrev = $event->tournamentStages()
                        ->where('occurrence_id', $autoNext->occurrence_id)
                        ->where('sort_order', '<', $autoNext->sort_order)
                        ->orderByDesc('sort_order')
                        ->first();

                    if ($autoPrev && $autoPrev->isCompleted()) {
                        try {
                            DB::transaction(function () use ($autoNext, $autoPrev) {
                                $this->setupService->launchStage($autoNext->fresh(), $autoPrev, []);
                            });
                            \Log::warning("Автозапуск bracket-стадии {$autoNext->id} после завершения группы {$stage->id}");
                        } catch (\Throwable $e) {
                            TournamentStage::where('id', $autoNext->id)
                                ->update(['status' => TournamentStage::STATUS_PENDING]);
                            \Log::warning("Ошибка автозапуска bracket-стадии {$autoNext->id}, статус возвращён в pending: " . $e->getMessage());
                        }
                    } else {
                        // предшественник ещё не завершён (не должно происходить, т.к. мы
                        // сюда попадаем именно из завершения группы) — откатить claim
                        TournamentStage::where('id', $autoNext->id)
                            ->update(['status' => TournamentStage::STATUS_PENDING]);
                    }
                }
            }

            // 'divisions' (финальные группы по уровням) — эта группа завершена,
            // но турнир НЕ закончен: для divisions companion-стадия сознательно
            // не создаётся при createStage() (см. комментарий там), поэтому без
            // этой проверки завершённая группа была бы ЕДИНСТВЕННОЙ batch-стадией
            // и турнир закрылся бы сразу — тот же баг, что чинили для event 402.
            // Ждём, пока организатор явно нажмёт "Сформировать группы" на пульте
            // (formDivisions() создаёт стадии "Группа Hard/Lite/...") — до этого
            // момента считаем турнир незавершённым.
            if (
                $stage->canHaveFollowupStage()
                && $stage->cfg('finals_mode') === 'divisions'
                && $stage->groups->count() >= 2
                && !$event->tournamentStages()->where('name', 'like', 'Группа %')->exists()
            ) {
                return;
            }

            // Инкрементальные форматы (swiss/king_of_court/king_beach) генерируют матчи
            // по ходу турнира — "все СОЗДАННЫЕ на сейчас матчи сыграны" (наивный критерий
            // строкой выше, применённый к $stage) ложно совпадает с "формат закончен":
            // king_of_court может закрыться после первого же матча (очередь ещё не
            // пуста, просто следующий матч ещё не сгенерирован), swiss — после первого
            // раунда, до клика "Следующий тур" (см. report_stage_type_branching_audit.md
            // §3/§4.4). Поэтому при решении "весь ТУРНИР завершён" инкрементальные
            // стадии не учитываются вовсе, если в событии есть хотя бы одна batch/bracket
            // стадия (round_robin/groups_playoff/single_elim/double_elim/thai) —
            // завершённость турнира определяется ТОЛЬКО ими.
            $stages = $event->tournamentStages()->get();
            $batchStages = $stages->reject(fn($s) => $s->hasIncrementalMatchGeneration());

            if ($batchStages->isEmpty()) {
                // Событие целиком состоит из инкрементальных стадий (например, один
                // swiss/king_of_court/king_beach без companion-стадии) — консервативно
                // НЕ закрываем турнир автоматически вообще: лучше не закрыть вовремя,
                // чем закрыть раньше времени. ИЗВЕСТНОЕ ОГРАНИЧЕНИЕ: для таких турниров
                // "Турнир завершён!" не отправится автоматически никогда — явная кнопка
                // "Завершить стадию" для организатора остаётся в backlog
                // (report_stage_type_branching_audit.md §4.4), не реализована в этом проходе.
                return;
            }

            $allStages = $batchStages->count();
            $completedStages = $batchStages->filter(fn($s) => $s->isCompleted())->count();

            if ($allStages > 0 && $allStages === $completedStages) {
                try {
                    // Атомарный claim: "турнир завершён" — событие, которое должно
                    // произойти РОВНО ОДИН РАЗ за жизнь турнира. Без guard'а этот
                    // блок срабатывает при КАЖДОМ переходе allStages===completedStages
                    // в true — если в уже завершённый турнир позже дозаполняется и
                    // завершается ещё один матч (например, дозаполнение плей-офф
                    // матчем за 3-4 место), участники получали бы повторное
                    // "Турнир завершён!" уведомление. Тот же паттерн, что и
                    // events.city_notified_at.
                    $claimed = DB::table('events')
                        ->where('id', $event->id)
                        ->whereNull('tournament_completed_notified_at')
                        ->update(['tournament_completed_notified_at' => now()]);

                    if ($claimed) {
                        app(\App\Services\TournamentNotificationService::class)
                            ->notifyTournamentCompleted($event);
                    }

                    // Пересчёт статистики/Elo/OpenSkill НЕ дублируем здесь: единственные
                    // реальные вызывающие пути этого метода — score()/rescoreMatch() — уже
                    // ставят RecalculateTournamentStatsJob (обёртка над recalculateTournament(),
                    // сама ставит ratings job внутри себя) в очередь непосредственно перед
                    // checkStageCompletion() в том же запросе — ShouldBeUnique по event_id
                    // и так схлопнул бы повторный dispatch, но лишний вызов здесь не нужен.
                    // (scoreKingBeach() тоже зовёт этот метод, но не через team_home_id/
                    // winner_team_id — для king_beach recalculateTournament() безрезультатен
                    // и раньше не вызывался вовсе, см. отдельный backlog.)

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
     * PDF-выгрузка статистики матча (доступна, когда счёт по партиям внесён).
     */
    public function pdfMatchStats(Request $request, TournamentMatch $match)
    {
        $stage = $match->stage;
        $event = $stage->event;
        $this->authorizeOrganizer($request, $event);

        if (!$match->isCompleted()) {
            return redirect()
                ->route('tournament.matches.rally.form', $match)
                ->with('error', 'Статистика будет доступна для выгрузки после завершения матча.');
        }

        $match->load(['teamHome.members.user', 'teamHome.captain', 'teamAway.members.user', 'teamAway.captain', 'stage']);

        $statsData = app(PlayerMatchStatsService::class)->getMatchStatsTable($match);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('tournaments.pdf.match_stats', compact('event', 'stage', 'match', 'statsData'))
            ->setPaper('a4', 'portrait');

        $filename = 'match_' . $match->id . '_stats_' . now()->format('Ymd') . '.pdf';
        return $pdf->download($filename);
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
     *  Поочковый ввод счёта со статистикой (rally-режим)
     * ================================================================ */

    private function guardRallyMode(Request $request, TournamentMatch $match): ?RedirectResponse
    {
        $stage = $match->stage;
        $event = $stage->event;
        $this->authorizeOrganizer($request, $event);

        if ($stage->isPlayerBasedMatches()) {
            return redirect()
                ->route('tournament.matches.score.form', $match)
                ->with('error', 'Поочковый ввод недоступен для формата "Король пляжа".');
        }
        if ($match->isCompleted()) {
            return redirect()
                ->route('tournament.matches.player_stats.form', $match)
                ->with('error', 'Матч уже завершён.');
        }
        if (!$match->hasTeams()) {
            return redirect()
                ->route('tournament.matches.score.form', $match)
                ->with('error', 'У матча не назначены команды.');
        }

        return null;
    }

    public function rallyForm(Request $request, TournamentMatch $match)
    {
        if ($redirect = $this->guardRallyMode($request, $match)) {
            return $redirect;
        }

        $stage = $match->stage;
        $event = $stage->event;
        $match->load(['teamHome.members.user', 'teamHome.captain', 'teamAway.members.user', 'teamAway.captain', 'stage']);

        $activeSet = $this->rallyService->getActiveSetNumber($match);
        $setNumber = (int) $request->query('set', $activeSet);
        if ($setNumber < 1) {
            $setNumber = $activeSet;
        }

        $board = $this->rallyService->getBoard($match, $setNumber);
        $matchReady = $this->rallyService->isMatchReadyToFinalize($match);

        return view('tournaments.score_rally', compact('event', 'match', 'stage', 'board', 'setNumber', 'matchReady'));
    }

    public function rallyRecordPoint(Request $request, TournamentMatch $match)
    {
        if ($redirect = $this->guardRallyMode($request, $match)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Поочковый ввод недоступен для этого матча.'], 422);
            }
            return $redirect;
        }

        $validated = $request->validate([
            'set_number'      => 'required|integer|min:1|max:5',
            'team_id'         => 'required|integer',
            'action_type'     => 'required|string',
            'player_id'       => 'nullable|integer',
            'dig_user_id'     => 'nullable|integer',
            'assist_user_id'  => 'nullable|integer',
        ]);

        try {
            $this->rallyService->recordPoint(
                $match,
                (int) $validated['set_number'],
                (int) $validated['team_id'],
                $validated['action_type'],
                isset($validated['player_id']) ? (int) $validated['player_id'] : null,
                isset($validated['dig_user_id']) ? (int) $validated['dig_user_id'] : null,
                isset($validated['assist_user_id']) ? (int) $validated['assist_user_id'] : null,
                $request->user(),
            );
        } catch (\InvalidArgumentException $e) {
            if ($request->expectsJson()) {
                return response()->json(['error' => $e->getMessage()], 422);
            }
            return redirect()
                ->route('tournament.matches.rally.form', [$match, 'set' => $validated['set_number']])
                ->with('error', $e->getMessage());
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'board'   => $this->rallyService->getBoard($match->fresh(), (int) $validated['set_number']),
            ]);
        }

        return redirect()->route('tournament.matches.rally.form', [$match, 'set' => $validated['set_number']]);
    }

    public function rallyUndoLastPoint(Request $request, TournamentMatch $match)
    {
        if ($redirect = $this->guardRallyMode($request, $match)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Поочковый ввод недоступен для этого матча.'], 422);
            }
            return $redirect;
        }

        $validated = $request->validate([
            'set_number' => 'required|integer|min:1|max:5',
        ]);

        try {
            $this->rallyService->undoLastPoint($match, (int) $validated['set_number']);
        } catch (\InvalidArgumentException $e) {
            if ($request->expectsJson()) {
                return response()->json(['error' => $e->getMessage()], 422);
            }
            return redirect()
                ->route('tournament.matches.rally.form', [$match, 'set' => $validated['set_number']])
                ->with('error', $e->getMessage());
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'board'   => $this->rallyService->getBoard($match->fresh(), (int) $validated['set_number']),
            ]);
        }

        return redirect()->route('tournament.matches.rally.form', [$match, 'set' => $validated['set_number']]);
    }

    public function rallyFinalize(Request $request, TournamentMatch $match)
    {
        if ($redirect = $this->guardRallyMode($request, $match)) {
            return $redirect;
        }

        if (!$this->rallyService->isMatchReadyToFinalize($match)) {
            return redirect()
                ->route('tournament.matches.rally.form', $match)
                ->with('error', 'Матч ещё не завершён по счёту.');
        }

        $sets = $this->rallyService->buildFinalSets($match);

        // Переиспользуем существующий score() целиком (валидация/standings/
        // тайбрейкер/checkStageCompletion/редирект на следующий матч) —
        // без дублирования логики.
        $request->merge(['sets' => $sets]);

        return $this->score($request, $match);
    }

    /**
     * Переоткрыть завершённый матч для правки через поочковый ввод.
     * Сбрасывает счёт/статус матча (как rescoreMatch). Если у матча уже
     * есть накопленные match_rally_events (счёт вводился по очкам) — они
     * НЕ трогаются, организатор может отменить неверные очки и добавить
     * верные. Если рали-данных нет (счёт вводился обычной формой по сетам) —
     * организатор вводит матч заново с нуля по очкам. В обоих случаях
     * завершение — через «Записать счёт» (rallyFinalize), который проведёт
     * submitScore() + rebuildAll() как обычно.
     */
    public function rallyReopen(Request $request, TournamentMatch $match)
    {
        $stage = $match->stage;
        $event = $stage->event;
        $this->authorizeOrganizer($request, $event);

        if ($stage->isPlayerBasedMatches()) {
            return redirect()
                ->route('tournament.matches.score.form', $match)
                ->with('error', 'Поочковый ввод недоступен для формата "Король пляжа".');
        }

        if (!$match->isCompleted()) {
            return redirect()->route('tournament.matches.rally.form', $match);
        }

        $stageIsDivStage = $stage->division_tier !== null || str_starts_with($stage->name, 'Группа ');
        if (!$stageIsDivStage) {
            $hasDivStages = $event->tournamentStages()
                ->where('occurrence_id', $stage->occurrence_id)
                ->where(fn($q) => $q->whereNotNull('division_tier')
                    ->orWhere('name', 'like', 'Группа %'))
                ->exists();
            if ($hasDivStages) {
                return redirect()
                    ->route('tournament.matches.score.form', $match)
                    ->with('error', 'Нельзя исправить счёт — группы уже сформированы. Откатите распределение и повторите.');
            }
        }

        $this->matchService->resetScore($match);

        // Обычно следом идёт rallyFinalize()→score(), который сам поставит пересчёт
        // в очередь — но если организатор не доведёт до конца (уйдёт со страницы),
        // сброшенный счёт иначе останется учтён в player_tournament_stats/OpenSkill
        // до следующего чужого триггера в этом же событии. ShouldBeUnique схлопнёт
        // этот dispatch с последующим из rallyFinalize(), если он всё же случится.
        try {
            \App\Jobs\RecalculateTournamentStatsJob::dispatch($event->id)->afterCommit();
        } catch (\Throwable $e) {
            \Log::warning('Stats rebuild dispatch after rally reopen failed: ' . $e->getMessage());
        }

        return redirect()
            ->route('tournament.matches.rally.form', $match)
            ->with('success', 'Матч временно переоткрыт для правки. После исправления нажмите «Записать счёт».');
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

    /**
     * PNG-карточка матча для шаринга (1200x630, Browsershot). Публичный роут —
     * авторизация не нужна, доступ только по завершённому матчу. Кешируется на
     * диске, перегенерируется только если матч обновлялся позже файла.
     */
    public function shareCard(TournamentMatch $match)
    {
        abort_unless($match->status === TournamentMatch::STATUS_COMPLETED, 404);

        $match->loadMissing(['teamHome.captain', 'teamAway.captain', 'stage.event.location']);

        $path = storage_path("app/public/share-cards/match-{$match->id}.png");

        if (!file_exists($path) || filemtime($path) < $match->updated_at->timestamp) {
            if (!is_dir(dirname($path))) {
                mkdir(dirname($path), 0775, true);
            }

            $html = view('tournaments._partials.match_share_card', [
                'match' => $match,
            ])->render();

            $browsershot = \Spatie\Browsershot\Browsershot::html($html)
                ->windowSize(1200, 630)
                ->deviceScaleFactor(2)
                ->noSandbox();

            // Chrome лежит в storage/app/chromium (не в ~/.cache/puppeteer — туда
            // php-fpm/www-data не имеет доступа, см. CLAUDE.md). Версия в пути не
            // хардкодится — берём что реально установлено.
            $chromeBinaries = glob(storage_path('app/chromium/*/chrome-linux64/chrome'));
            if (!empty($chromeBinaries)) {
                $browsershot->setChromePath($chromeBinaries[0]);
            }

            $browsershot->save($path);
        }

        return response()->file($path, [
            'Content-Type'  => 'image/png',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
