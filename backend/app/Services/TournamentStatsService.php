<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventTeam;
use App\Models\TournamentMatch;
use App\Models\TournamentStage;
use App\Models\TournamentStanding;
use App\Models\PlayerTournamentStats;
use App\Models\PlayerCareerStats;
use App\Models\EventTeamMember;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use App\Jobs\RecalculateTournamentRatingsJob;

class TournamentStatsService
{
    /**
     * Обновить статистику всех игроков после завершённого матча.
     */
    public function updateAfterMatch(TournamentMatch $match): void
    {
        if (! $match->isCompleted() || ! $match->winner_team_id) return;

        $stage = $match->stage;
        $event = $stage->event;

        // Обновляем stats для обеих команд
        foreach ([
            ['team_id' => $match->team_home_id, 'won' => $match->sets_home, 'lost' => $match->sets_away,
             'scored' => $match->total_points_home, 'conceded' => $match->total_points_away,
             'isWinner' => $match->winner_team_id === $match->team_home_id],
            ['team_id' => $match->team_away_id, 'won' => $match->sets_away, 'lost' => $match->sets_home,
             'scored' => $match->total_points_away, 'conceded' => $match->total_points_home,
             'isWinner' => $match->winner_team_id === $match->team_away_id],
        ] as $side) {
            $this->updateTeamPlayersStats($event, $side['team_id'], $side);
        }

        // OpenSkill mu/sigma больше НЕ обновляются здесь инкрементально (был источник
        // задвоения при коррекции счёта уже обработанного матча — исправление счёта
        // сбрасывало stats_processed_at, но mu/sigma пересчитывались поверх текущего,
        // уже включающего старую дельту, значения). Единственный источник правды теперь —
        // TournamentOpenSkillService::rebuildAll() (полная переигровка с нуля), вызывается
        // из recalculateTournament() ниже через RecalculateTournamentRatingsJob.
    }

    /**
     * Обновить stats для всех игроков команды.
     */
    private function updateTeamPlayersStats(Event $event, int $teamId, array $side): void
    {
        $memberUserIds = DB::table('event_team_members')
            ->where('event_team_id', $teamId)
            ->where('confirmation_status', 'confirmed')
            ->pluck('user_id');

        foreach ($memberUserIds as $userId) {
            $stat = PlayerTournamentStats::firstOrCreate([
                'event_id' => $event->id,
                'user_id'  => $userId,
                'team_id'  => $teamId,
            ]);

            $stat->matches_played++;
            if ($side['isWinner']) $stat->matches_won++;
            $stat->sets_won        += $side['won'];
            $stat->sets_lost       += $side['lost'];
            $stat->points_scored   += $side['scored'];
            $stat->points_conceded += $side['conceded'];

            $stat->recalcRates()->save();
        }
    }

    /**
     * Пересчитать career stats для игрока по всем его турнирам.
     */
    public function rebuildCareerStats(int $userId): void
    {
        foreach (['classic', 'beach'] as $direction) {
            $tournamentStats = PlayerTournamentStats::where('user_id', $userId)
                ->whereHas('event', fn($q) => $q->where('direction', $direction))
                ->get();

            if ($tournamentStats->isEmpty()) {
                PlayerCareerStats::where('user_id', $userId)
                    ->where('direction', $direction)
                    ->delete();
                continue;
            }

            $career = PlayerCareerStats::firstOrCreate([
                'user_id'   => $userId,
                'direction' => $direction,
            ]);

            $career->total_tournaments    = $tournamentStats->groupBy('event_id')->count();
            $career->total_matches        = $tournamentStats->sum('matches_played');
            $career->total_wins           = $tournamentStats->sum('matches_won');
            $career->total_sets_won       = $tournamentStats->sum('sets_won');
            $career->total_sets_lost      = $tournamentStats->sum('sets_lost');
            $career->total_points_scored  = $tournamentStats->sum('points_scored');
            $career->total_points_conceded = $tournamentStats->sum('points_conceded');

            $career->recalcRates()->save();
        }
    }

    /**
     * Пересчитать career stats для всех игроков турнира.
     */
    public function rebuildAllCareerStatsForEvent(Event $event): void
    {
        $userIds = PlayerTournamentStats::where('event_id', $event->id)
            ->pluck('user_id')
            ->unique();

        foreach ($userIds as $userId) {
            $this->rebuildCareerStats($userId);
        }
    }

    /**
     * Полный пересчёт player_tournament_stats для турнира с нуля.
     */
    public function rebuildTournamentStats(Event $event): void
    {
        // Очищаем
        PlayerTournamentStats::where('event_id', $event->id)->delete();

        // Проходим все завершённые матчи
        $matches = TournamentMatch::whereHas('stage', fn($q) => $q->where('event_id', $event->id))
            ->where('status', TournamentMatch::STATUS_COMPLETED)
            ->get();

        foreach ($matches as $match) {
            $this->updateAfterMatch($match);
        }
    }

    /**
     * Полный пересчёт всей статистики события: PTS → career → season.
     * Используется после каждого сохранения/изменения счёта матча.
     */
    public function rebuildAll(Event $event): void
    {
        // Собираем всех участников команд события (до пересчёта), чтобы обновить их career
        $affectedUserIds = DB::table('event_team_members')
            ->join('event_teams', 'event_teams.id', '=', 'event_team_members.event_team_id')
            ->where('event_teams.event_id', $event->id)
            ->pluck('event_team_members.user_id')
            ->merge(PlayerTournamentStats::where('event_id', $event->id)->pluck('user_id'))
            ->unique();

        $this->rebuildTournamentStats($event);

        foreach ($affectedUserIds as $userId) {
            $this->rebuildCareerStats($userId);
        }

        if ($event->season_id) {
            $season = $event->season;
            if ($season) {
                App::make(TournamentSeasonStatsService::class)->rebuildForSeason($season);
            }
        }
    }

    /**
     * Единая точка входа "полностью пересчитать турнир из текущих результатов матчей".
     * Вызывается на КАЖДОМ пути, меняющем набор/содержимое результатов события: первый
     * ввод счёта, исправление счёта, откат стадии, удаление стадии — единый путь, без
     * отдельного "инкрементального" варианта (см. report_recalc_implementation_plan).
     *
     * 1) event-scoped часть (player_tournament_stats/career-totals) — полный delete+rebuild,
     *    уже безопасна сама по себе (rebuildAll($event)).
     * 2) standings ВСЕХ групп события — submitScore()/resetScore() пересчитывают только
     *    группу конкретного матча; revertStage()/destroyStage() вообще не проходят через
     *    них, поэтому здесь явно прогоняем все группы турнира.
     * 3) Elo/OpenSkill — общекарьерные, path-dependent (см. TournamentEloService::rebuildAll())
     *    — корректно пересчитываются только полной переигровкой ВСЕЙ платформы, не одного
     *    турнира, поэтому это отдельная асинхронная job, а не часть этого метода.
     */
    public function recalculateTournament(Event $event): void
    {
        $this->rebuildAll($event);

        $standingsService = App::make(TournamentStandingsService::class);
        foreach ($event->tournamentStages as $stage) {
            foreach ($stage->groups as $group) {
                $standingsService->recalculateGroup($stage, $group);
            }
        }

        RecalculateTournamentRatingsJob::dispatch($event->id)->afterCommit();
    }

    /**
     * Получить топ игроков турнира по match_win_rate.
     */
    public function getTopPlayers(int $eventId, int $limit = 10): \Illuminate\Support\Collection
    {
        return PlayerTournamentStats::where('event_id', $eventId)
            ->where('matches_played', '>', 0)
            ->with('user', 'team')
            ->orderByDesc('match_win_rate')
            ->orderByDesc('point_diff')
            ->limit($limit)
            ->get();
    }

    /**
     * Таблица рейтинга участников турнира в стиле /players/rating: турнирная
     * статистика (игры/победы/очки) + общий рейтинг (CR/игры/winrate), формулы
     * CR и winrate — те же, что в PlayerRatingController/players.rating.blade.php
     * (max(0, mu - 3*sigma), wins/matches*100), без дублирования — переиспользуют
     * ту же логику расчёта.
     *
     * Ростер — участники команд турнира (event_team_members), не только те,
     * у кого уже есть player_tournament_stats (игрок мог ещё не сыграть).
     *
     * @return array{rows: array, hasPoints: bool, hiddenCount: int, direction: string}
     */
    public function getParticipantRatingTable(Event $event): array
    {
        $direction = $event->direction;

        $roster = DB::table('event_team_members')
            ->join('event_teams', 'event_teams.id', '=', 'event_team_members.event_team_id')
            ->where('event_teams.event_id', $event->id)
            ->where('event_team_members.confirmation_status', 'confirmed')
            ->select('event_team_members.user_id', 'event_teams.name as team_name')
            ->get()
            ->unique('user_id')
            ->keyBy('user_id');

        if ($roster->isEmpty()) {
            return ['rows' => [], 'hasPoints' => false, 'hiddenCount' => 0, 'direction' => $direction];
        }

        $userIds = $roster->keys()->all();

        $tournamentStats = PlayerTournamentStats::where('event_id', $event->id)
            ->whereIn('user_id', $userIds)
            ->get()
            ->keyBy('user_id');

        $pointsByUser = app(PlayerMatchStatsService::class)->sumPointsScoredForEvent($event->id);
        $hasPoints = $pointsByUser->sum() > 0;

        $careerStats = PlayerCareerStats::where('direction', $direction)
            ->whereIn('user_id', $userIds)
            ->get()
            ->keyBy('user_id');

        $users = User::whereIn('id', $userIds)->get()->keyBy('id');

        $rows = [];
        foreach ($roster as $userId => $r) {
            $ts = $tournamentStats->get($userId);
            $cs = $careerStats->get($userId);

            $mu = $cs?->mu ?? 25.0;
            $sigma = $cs?->sigma ?? 8.333;
            $oGames = $cs?->total_matches ?? 0;
            $oWins = $cs?->total_wins ?? 0;

            $rows[] = [
                'user_id'   => $userId,
                'user'      => $users->get($userId),
                'team_name' => $r->team_name,
                't_games'   => $ts?->matches_played ?? 0,
                't_wins'    => $ts?->matches_won ?? 0,
                't_points'  => (int) $pointsByUser->get($userId, 0),
                'cr'        => max(0.0, $mu - 3 * $sigma),
                'o_games'   => $oGames,
                'o_winrate' => $oGames > 0 ? round($oWins / $oGames * 100, 1) : 0.0,
            ];
        }

        $played = array_values(array_filter($rows, fn($r) => $r['t_games'] > 0));
        $hiddenCount = count($rows) - count($played);

        // Тай-брейк по победам добавлен для более точного ранжирования при
        // равенстве очков — MVP турнира сюда не относится: он назначается
        // организатором вручную (Event::tournament_mvp_user_id, см. setup.blade.php).
        usort($played, fn($a, $b) => $hasPoints
            ? ($b['t_points'] <=> $a['t_points']) ?: ($b['t_wins'] <=> $a['t_wins']) ?: ($b['cr'] <=> $a['cr'])
            : ($b['cr'] <=> $a['cr']));

        return [
            'rows'        => $played,
            'hasPoints'   => $hasPoints,
            'hiddenCount' => $hiddenCount,
            'direction'   => $direction,
        ];
    }

    /**
     * Определить итоговую классификацию (1–N место) после завершения турнира.
     *
     * Логика:
     * 1. Если есть single_elim/double_elim стадия → победитель финала = 1 место, проигравший = 2
     * 2. Если есть матч за 3-е → его победитель = 3
     * 3. Остальные — по standings последней групповой стадии
     *
     * @return array<int, array{place: int, team_id: int, team_name: string}>
     */
    public function calculateFinalClassification(Event $event, ?int $occurrenceId = null): array
    {
        $stages = $event->tournamentStages()
            ->when($occurrenceId, fn($q) => $q->where('occurrence_id', $occurrenceId))
            ->orderBy('sort_order')->get();
        $classification = [];
        $place = 1;
        $assignedTeams = [];

        // Если есть дивизионные стадии (Hard/Medium/Lite) — классификация по ним
        // (Hard первый, потом Medium, потом Lite). division_tier — основной признак;
        // паттерн по имени "Группа " — фоллбэк для стадий без backfill (см.
        // report_division_tier_migration_plan_2026-08-04.md). division_tier также
        // ловит king_beach-дивизионы (называются без префикса "Группа ") — но ниже
        // есть защита: если для найденных "дивизионных" стадий не набралось ни одной
        // строки classification (у king_beach standings лежат в KingBeachStanding, а
        // не TournamentStanding — эта ветка их не читает), проваливаемся в остальную
        // логику метода вместо возврата пустого результата.
        $divisionStages = $stages->filter(fn($s) => $s->division_tier !== null || str_starts_with($s->name, 'Группа '));
        if ($divisionStages->isNotEmpty()) {
            // Сортируем: Hard первый, Medium потом, Lite последний
            $sorted = $divisionStages->sortBy(function($s) {
                if ($s->division_tier !== null) return $s->division_tier;
                if (str_contains($s->name, 'Hard')) return 0;
                if (str_contains($s->name, 'Medium')) return 1;
                return 2; // Lite
            });

            foreach ($sorted as $divStage) {
                $divStandings = TournamentStanding::where('stage_id', $divStage->id)
                    ->with('team')
                    ->orderBy('rank')
                    ->get();

                foreach ($divStandings as $s) {
                    if (in_array($s->team_id, $assignedTeams)) continue;
                    $classification[] = [
                        'place' => $place++,
                        'team_id' => $s->team_id,
                        'team_name' => $s->team->name ?? '?',
                        'division' => str_replace('Группа ', '', $divStage->name),
                        'wins' => $s->wins,
                        'losses' => $s->losses,
                        'rating_points' => $s->rating_points,
                        'points_scored' => $s->points_scored,
                        'points_conceded' => $s->points_conceded,
                    ];
                    $assignedTeams[] = $s->team_id;
                }
            }

            if (!empty($classification)) {
                return $classification;
            }
        }

        // 1. Bracket стадия (single/double elim) — финал определяет 1-2 место
        $bracketStage = $stages->filter(fn($s) => $s->isBracketStage())->last();
        if ($bracketStage && $bracketStage->isPlacementFinal()) {
            // Финал за места напрямую (crossover, finals_mode='placement') — два
            // равноправных матча первого раунда без иерархии bracket-раундов,
            // различаем по placeFrom ("за 1-2 место" / "за 3-4 место"), а не по
            // round/match_number и не по литералу '3rd place' (см. ветку else —
            // это ровно то, что было причиной неверного победителя у event 402,
            // report_402_finals_bug.md).
            $finalMatch = $bracketStage->placementMatch(1);
            if ($finalMatch && $finalMatch->status === TournamentMatch::STATUS_COMPLETED && $finalMatch->winner_team_id) {
                $winner = EventTeam::find($finalMatch->winner_team_id);
                $loser = EventTeam::find($finalMatch->loserId());

                if ($winner) {
                    $classification[] = ['place' => $place++, 'team_id' => $winner->id, 'team_name' => $winner->name];
                    $assignedTeams[] = $winner->id;
                }
                if ($loser) {
                    $classification[] = ['place' => $place++, 'team_id' => $loser->id, 'team_name' => $loser->name];
                    $assignedTeams[] = $loser->id;
                }
            }

            $thirdMatch = $bracketStage->placementMatch(3);
            if ($thirdMatch && $thirdMatch->status === TournamentMatch::STATUS_COMPLETED && $thirdMatch->winner_team_id) {
                $third = EventTeam::find($thirdMatch->winner_team_id);
                $fourth = EventTeam::find($thirdMatch->loserId());

                if ($third && !in_array($third->id, $assignedTeams)) {
                    $classification[] = ['place' => $place++, 'team_id' => $third->id, 'team_name' => $third->name];
                    $assignedTeams[] = $third->id;
                }
                if ($fourth && !in_array($fourth->id, $assignedTeams)) {
                    $classification[] = ['place' => $place++, 'team_id' => $fourth->id, 'team_name' => $fourth->name];
                    $assignedTeams[] = $fourth->id;
                }
            }
        } elseif ($bracketStage) {
            // Обычная сетка (finals_mode='bracket') — финал и матч за 3-е место
            // (court='3rd place', TournamentBracketService::generateSingleElimination())
            // оказываются в ОДНОМ round у последнего раунда сетки, а матч за 3-е
            // место создаётся ПОСЛЕ цикла по раундам — то есть получает БОЛЬШИЙ
            // match_number, чем сам финал. Сортировка по match_number при равном
            // round раньше выбирала именно матч за 3-е как "финал" (БАГ 4, найден
            // при регресс-тестировании фикса event 402, см. report_402_finals_fix.md
            // п.4) — единственный надёжный признак финала — явное исключение
            // court='3rd place' из выбора, а не порядок номеров матчей.
            $finalMatch = $bracketStage->matches()
                ->where('status', TournamentMatch::STATUS_COMPLETED)
                ->where(function ($q) {
                    $q->whereNull('court')->orWhere('court', '!=', '3rd place');
                })
                ->orderByDesc('round')
                ->orderByDesc('match_number')
                ->first();

            if ($finalMatch && $finalMatch->winner_team_id) {
                $winner = EventTeam::find($finalMatch->winner_team_id);
                $loser = EventTeam::find($finalMatch->loserId());

                if ($winner) {
                    $classification[] = ['place' => $place++, 'team_id' => $winner->id, 'team_name' => $winner->name];
                    $assignedTeams[] = $winner->id;
                }
                if ($loser) {
                    $classification[] = ['place' => $place++, 'team_id' => $loser->id, 'team_name' => $loser->name];
                    $assignedTeams[] = $loser->id;
                }

                // Матч за 3 место
                $thirdMatch = $bracketStage->matches()
                    ->where('status', TournamentMatch::STATUS_COMPLETED)
                    ->where('court', '3rd place')
                    ->first();

                if ($thirdMatch && $thirdMatch->winner_team_id) {
                    $third = EventTeam::find($thirdMatch->winner_team_id);
                    $fourth = EventTeam::find($thirdMatch->loserId());

                    if ($third && !in_array($third->id, $assignedTeams)) {
                        $classification[] = ['place' => $place++, 'team_id' => $third->id, 'team_name' => $third->name];
                        $assignedTeams[] = $third->id;
                    }
                    if ($fourth && !in_array($fourth->id, $assignedTeams)) {
                        $classification[] = ['place' => $place++, 'team_id' => $fourth->id, 'team_name' => $fourth->name];
                        $assignedTeams[] = $fourth->id;
                    }
                }
            }
        }

        // 2. Оставшиеся команды — по standings последней ЗАВЕРШЁННОЙ стадии с группами.
        // Если групп несколько (Группа A/B/...) — выводим каждую как отдельный «дивизион»,
        // чтобы UI показал колонки с локальным ранжированием внутри группы.
        // Строгий гейт isCompleted(): у незавершённой стадии standings — пустой каркас
        // (rank=1 у всех, played=0), сформированный сразу при жеребьёвке/создании группы,
        // а не реальный результат — без гейта подиум рисовался на несыгранном турнире
        // (диагностика: report/diagnosis_bug7_bug8_event404_2026-08-14.md, event 404).
        $groupStage = $stages->filter(fn($s) => $s->groups->isNotEmpty() && $s->isCompleted())->last();
        if ($groupStage) {
            $stageGroups = $groupStage->groups->sortBy('name')->values();

            if ($stageGroups->count() > 1) {
                foreach ($stageGroups as $g) {
                    $standings = TournamentStanding::where('stage_id', $groupStage->id)
                        ->where('group_id', $g->id)
                        ->with('team')
                        ->orderBy('rank')
                        ->get();

                    foreach ($standings as $s) {
                        if (in_array($s->team_id, $assignedTeams)) continue;
                        $classification[] = [
                            'place' => $place++,
                            'team_id' => $s->team_id,
                            'team_name' => $s->team->name ?? '?',
                            'division' => $g->name,
                            'wins' => $s->wins,
                            'losses' => $s->losses,
                            'rating_points' => $s->rating_points,
                            'points_scored' => $s->points_scored,
                            'points_conceded' => $s->points_conceded,
                        ];
                        $assignedTeams[] = $s->team_id;
                    }
                }
            } else {
                $standings = TournamentStanding::where('stage_id', $groupStage->id)
                    ->with('team')
                    ->orderBy('rank')
                    ->get();

                foreach ($standings as $s) {
                    if (in_array($s->team_id, $assignedTeams)) continue;
                    $classification[] = ['place' => $place++, 'team_id' => $s->team_id, 'team_name' => $s->team->name ?? '?'];
                    $assignedTeams[] = $s->team_id;
                }
            }
        }

        // 3. Все оставшиеся команды
        $allTeamIds = DB::table('event_teams')
            ->where('event_id', $event->id)
            ->where('status', 'submitted')
            ->pluck('id');

        foreach ($allTeamIds as $tid) {
            if (in_array($tid, $assignedTeams)) continue;
            $team = EventTeam::find($tid);
            $classification[] = ['place' => $place++, 'team_id' => $tid, 'team_name' => $team->name ?? '?'];
        }

        return $classification;
    }

    /**
     * Кандидаты на MVP турнира — топ-1, спроецированный по типу финала:
     * дивизионы (division_tier) → по одному победителю с КАЖДОЙ финальной
     * группы (Hard/Medium/Lite); placement-финал/bracket-сетка → чемпион
     * (место 1); нет финальной стадии (только круговая) → 1-е место общей
     * таблицы. Переиспользует calculateFinalClassification() — единую точку
     * подсчёта мест, не отдельный подсчёт (та же классификация, что везде
     * на сайте даёт 1-2-3-4 место).
     *
     * Возвращает ВСЕХ подтверждённых участников команды(-победителей), даже
     * если у игрока нет ни одного завершённого матча (запасной, не выходивший
     * на площадку) — organizer выбирает MVP вручную из полного состава.
     * Для игроков без строки в player_tournament_stats возвращается заглушка
     * с нулевыми показателями (не сохраняется в БД).
     *
     * @return Collection<int, PlayerTournamentStats>
     */
    public function getMvpCandidates(Event $event, ?int $occurrenceId = null): Collection
    {
        $classification = collect($this->calculateFinalClassification($event, $occurrenceId));
        if ($classification->isEmpty()) {
            return collect();
        }

        // ВАЖНО: не определять "это дивизионы" по наличию ключа 'division' в
        // классификации — тот же ключ используется calculateFinalClassification()
        // и для ОБЫЧНОЙ круговой стадии с 2+ группами без финала вообще (там
        // 'division' = имя группы, "Группа A"/"Группа B", просто для отображения
        // мини-таблиц, а не финальный дивизион). Взятие rank=1 каждой такой
        // группы как "победителя" некорректно — при 2+ группах без финала нет
        // единого победителя турнира структурно (для этого и нужен финал/дивизии).
        // Поэтому "это дивизионы" проверяем структурно, по division_tier стадий
        // этого occurrence — тот же признак, что и в setup.blade.php/rescoreMatch().
        $hasDivisionTierStages = TournamentStage::where('event_id', $event->id)
            ->when($occurrenceId, fn($q) => $q->where('occurrence_id', $occurrenceId))
            ->where(fn($q) => $q->whereNotNull('division_tier')
                ->orWhere('name', 'like', 'Группа %'))
            ->exists();

        if ($hasDivisionTierStages) {
            // По одной команде-победителю (место 1 внутри дивизиона) с каждой финальной группы.
            $winnerTeamIds = $classification->groupBy('division')
                ->map(fn($rows) => collect($rows)->sortBy('place')->first()['team_id'])
                ->values()
                ->all();
        } else {
            $winnerEntry = $classification->firstWhere('place', 1);
            $winnerTeamIds = $winnerEntry ? [$winnerEntry['team_id']] : [];
        }

        if (empty($winnerTeamIds)) {
            return collect();
        }

        $statsRows = PlayerTournamentStats::where('event_id', $event->id)
            ->whereIn('team_id', $winnerTeamIds)
            ->with('user')
            ->get();

        $coveredUserIds = $statsRows->pluck('user_id')->all();

        $missingMembers = EventTeamMember::whereIn('event_team_id', $winnerTeamIds)
            ->where('confirmation_status', 'confirmed')
            ->whereNotIn('user_id', $coveredUserIds)
            ->with('user')
            ->get();

        $stubs = $missingMembers->map(function ($m) use ($event) {
            $stub = new PlayerTournamentStats([
                'event_id'       => $event->id,
                'user_id'        => $m->user_id,
                'team_id'        => $m->event_team_id,
                'matches_played' => 0,
                'matches_won'    => 0,
                'sets_won'       => 0,
                'sets_lost'      => 0,
                'match_win_rate' => 0,
            ]);
            $stub->setRelation('user', $m->user);
            return $stub;
        });

        return $statsRows->merge($stubs)->sortByDesc('match_win_rate')->values();
    }

}
