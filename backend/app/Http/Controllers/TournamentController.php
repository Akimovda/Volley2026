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
            ->where('status', 'submitted')
            ->with('captain')
            ->get();

        $pendingApplications = EventTeamApplication::where('event_id', $event->id)
            ->where('status', 'pending')
            ->with(['team.captain', 'team.members.user', 'submittedBy'])
            ->get();

        $settings = $event->tournamentSetting;
        $applicationMode = $settings->application_mode ?? 'manual';

        $userEventPhotos = $request->user()->getMedia('event_photos')->sortByDesc('created_at');

        return view('tournaments.setup', compact('event', 'stages', 'teams', 'pendingApplications', 'applicationMode', 'userEventPhotos'));
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

        $stage = $this->setupService->createStage($event, [
            'type'       => $validated['type'],
            'name'       => $validated['name'],
            'sort_order' => $sortOrder,
            'config'     => $config,
        ]);

        // Для Round Robin / Groups+Playoff — автосоздание групп
        if (in_array($validated['type'], ['round_robin', 'groups_playoff']) && $config['groups_count'] > 0) {
            $this->setupService->createGroupsAuto($stage, $config['groups_count']);
        }

        return redirect()
            ->route('tournament.setup', $event)
            ->with('success', "Стадия \"{$stage->name}\" создана.");
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
            ->where('status', 'submitted')
            ->get();

        // Фильтруем неоплаченные команды (если турнир платный)
        $payService = app(\App\Services\TournamentPaymentService::class);
        $eligibleTeams = $teams->filter(fn($t) => $payService->isTeamEligible($t));

        $unpaidCount = $teams->count() - $eligibleTeams->count();
        if ($unpaidCount > 0) {
            \Illuminate\Support\Facades\Log::info("Draw: {$unpaidCount} teams skipped (unpaid)");
        }

        $teams = $eligibleTeams->values();

        if ($teams->count() < 2) {
            return back()->with('error', 'Нужно минимум 2 подтверждённых команды.' . ($unpaidCount > 0 ? " ({$unpaidCount} команд не оплатили участие)" : ''));
        }

        $groups = $stage->groups;

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
                    $manualData[(int) $groupId] = array_map(fn($tid) => ['team_id' => (int) $tid], (array) $teamIds);
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

        return redirect()
            ->route('tournament.setup', $event)
            ->with('success', 'Жеребьёвка проведена, матчи сгенерированы.');
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

            // Проверяем, завершена ли стадия
            $this->checkStageCompletion($stage);

            if ($request->expectsJson()) {
                return response()->json(['success' => true, 'match' => $match->fresh()]);
            }

            return redirect()
                ->route('tournament.setup', $event)
                ->with('success', 'Счёт записан.');

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

        $match->load(['teamHome', 'teamAway', 'stage']);

        return view('tournaments.score', compact('event', 'match', 'stage'));
    }

    /* ================================================================
     *  Продвижение в плей-офф
     * ================================================================ */

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

            return redirect()
                ->route('tournament.setup', $event)
                ->with('success', 'Команды продвинуты в плей-офф.');

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
                return redirect()->route('tournament.setup', $event)
                    ->with('success', 'Тур ' . $matches->first()->round . ' сгенерирован (' . $matches->count() . ' матчей).');

            } elseif ($stage->type === 'king_of_court') {
                $match = $this->kingService->generateNextMatch($stage);
                if (!$match) {
                    return back()->with('error', 'Нет больше соперников в очереди.');
                }
                return redirect()->route('tournament.setup', $event)
                    ->with('success', 'Следующий матч King of the Court создан.');
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

            $stage->update(['status' => TournamentStage::STATUS_IN_PROGRESS]);
        });

        return redirect()->route('tournament.setup', $event)
            ->with('success', "Стадия \"{$stage->name}\" откачена — все счета сброшены.");
    }

        public function destroyStage(Request $request, TournamentStage $stage)
    {
        $event = $stage->event;
        $this->authorizeOrganizer($request, $event);

        $name = $stage->name;
        $stage->delete(); // cascadeOnDelete очистит groups, matches, standings

        return redirect()
            ->route('tournament.setup', $event)
            ->with('success', "Стадия \"{$name}\" удалена.");
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

        return redirect()->route('tournament.setup', $event)
            ->with('success', "Расписание сгенерировано: {$count} матчей.");
    }

        public function setMvp(Request $request, Event $event)
    {
        $this->authorizeOrganizer($request, $event);

        $validated = $request->validate([
            'mvp_user_id' => 'required|exists:users,id',
        ]);

        $event->update(['tournament_mvp_user_id' => $validated['mvp_user_id']]);

        return redirect()->route('tournament.setup', $event)
            ->with('success', 'MVP турнира установлен.');
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

            return redirect()->route('tournament.setup', $event)
                ->with('success', "Фото обновлены ({$count} шт.)");
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

        return redirect()->route('tournament.setup', $event)
            ->with('success', 'Фото загружены (' . count($request->file('photos')) . ' шт.)');
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

        return redirect()->route('tournament.setup', $event)->with('success', 'Фото удалено.');
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

        $application->team?->update(['status' => 'submitted']);

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

            // Проверяем, завершены ли ВСЕ стадии турнира
            $event = $stage->event;
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
}
