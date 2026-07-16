<?php

namespace App\Services;

use App\Models\MatchPlayerStats;
use App\Models\MatchRallyEvent;
use App\Models\TournamentMatch;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MatchRallyService
{
    public function __construct(
        protected PlayerMatchStatsService $playerStatsService,
    ) {}

    /**
     * Упрощённая read-only копия правила победы из TournamentMatchService::validateSet()
     * (app/Services/TournamentMatchService.php:296-320). Не идентична: там строгая
     * валидация уже готового финального счёта (включая ветку "если проигравший >=
     * target-1, разница обязана быть РОВНО 2" — для отлова опечаток вида "26:20").
     * При поточковом наращивании по одному очку "перепрыгнуть" момент победы
     * невозможно по построению — recordPoint() блокирует добавление очков после
     * того как это условие стало true, поэтому упрощённая монотонная проверка
     * достаточна и корректна именно для этого сценария.
     */
    public function isSetDecided(int $home, int $away, int $targetPoints): bool
    {
        $winner = max($home, $away);
        $loser  = min($home, $away);
        return $winner >= $targetPoints && ($winner - $loser) >= 2;
    }

    public function recordPoint(
        TournamentMatch $match,
        int $setNumber,
        int $scoringTeamId,
        string $actionType,
        ?int $playerId,
        ?int $digUserId,
        ?int $assistUserId,
        ?User $recordedBy,
    ): MatchRallyEvent {
        if ($match->isCompleted()) {
            throw new InvalidArgumentException('Матч уже завершён, поочковый ввод недоступен.');
        }
        if (!$match->hasTeams()) {
            throw new InvalidArgumentException('У матча не назначены команды.');
        }
        if (!in_array($scoringTeamId, [$match->team_home_id, $match->team_away_id], true)) {
            throw new InvalidArgumentException('Команда не участвует в этом матче.');
        }
        if (!in_array($actionType, MatchRallyEvent::ALL_ACTIONS, true)) {
            throw new InvalidArgumentException('Неизвестный тип действия.');
        }

        $opponentTeamId = $scoringTeamId === $match->team_home_id ? $match->team_away_id : $match->team_home_id;
        $players = $this->playerStatsService->getMatchPlayers($match);
        $rosterByTeam = [
            $match->team_home_id => array_column($players['home'], 'id'),
            $match->team_away_id => array_column($players['away'], 'id'),
        ];

        $statTeamId = null;
        if (in_array($actionType, MatchRallyEvent::SELF_ACTIONS, true)) {
            if (!$playerId || !in_array($playerId, $rosterByTeam[$scoringTeamId], true)) {
                throw new InvalidArgumentException('Для этого действия нужно указать игрока выигравшей команды.');
            }
            $statTeamId = $scoringTeamId;
        } elseif (in_array($actionType, MatchRallyEvent::OPP_ERROR_ACTIONS, true)) {
            if ($playerId === null && in_array($actionType, MatchRallyEvent::OPP_ERROR_ACTIONS_PLAYER_REQUIRED, true)) {
                throw new InvalidArgumentException('Для этого типа ошибки соперника нужно указать игрока.');
            }
            if ($playerId !== null) {
                if (!in_array($playerId, $rosterByTeam[$opponentTeamId], true)) {
                    throw new InvalidArgumentException('Игрок ошибки должен быть из команды соперника.');
                }
                $statTeamId = $opponentTeamId;
            }
        } else {
            $playerId = null;
        }

        if ($digUserId !== null && !in_array($digUserId, $rosterByTeam[$scoringTeamId], true)) {
            throw new InvalidArgumentException('Игрок приёма должен быть из выигравшей команды.');
        }
        if ($assistUserId !== null && !in_array($assistUserId, $rosterByTeam[$scoringTeamId], true)) {
            throw new InvalidArgumentException('Игрок передачи должен быть из выигравшей команды.');
        }

        return DB::transaction(function () use (
            $match, $setNumber, $scoringTeamId, $actionType, $playerId, $statTeamId,
            $digUserId, $assistUserId, $recordedBy
        ) {
            $locked = TournamentMatch::whereKey($match->id)->lockForUpdate()->first();

            $state = $this->computeMatchState($locked);
            $setInfo = $state['sets'][$setNumber] ?? null;
            if ($setInfo && $setInfo['decided']) {
                throw new InvalidArgumentException('Эта партия уже завершена.');
            }

            $teamPointNumber = MatchRallyEvent::where('match_id', $locked->id)
                ->where('set_number', $setNumber)
                ->where('team_id', $scoringTeamId)
                ->count() + 1;

            try {
                $event = MatchRallyEvent::create([
                    'match_id'            => $locked->id,
                    'set_number'          => $setNumber,
                    'team_id'             => $scoringTeamId,
                    'team_point_number'   => $teamPointNumber,
                    'action_type'         => $actionType,
                    'player_id'           => $playerId,
                    'stat_team_id'        => $statTeamId,
                    'dig_user_id'         => $digUserId,
                    'assist_user_id'      => $assistUserId,
                    'recorded_by_user_id' => $recordedBy?->id,
                ]);
            } catch (QueryException $e) {
                // Гонка по уникальному индексу — кто-то уже вставил этот же team_point_number
                $event = MatchRallyEvent::where('match_id', $locked->id)
                    ->where('set_number', $setNumber)
                    ->where('team_id', $scoringTeamId)
                    ->where('team_point_number', $teamPointNumber)
                    ->firstOrFail();
            }

            $this->syncPlayerStats($locked, $setNumber);

            if ($locked->status === TournamentMatch::STATUS_SCHEDULED) {
                $locked->update(['status' => TournamentMatch::STATUS_LIVE]);
            }

            return $event;
        });
    }

    public function undoLastPoint(TournamentMatch $match, int $setNumber): bool
    {
        if ($match->isCompleted()) {
            throw new InvalidArgumentException('Матч уже завершён.');
        }

        return DB::transaction(function () use ($match, $setNumber) {
            TournamentMatch::whereKey($match->id)->lockForUpdate()->first();

            $last = MatchRallyEvent::where('match_id', $match->id)
                ->where('set_number', $setNumber)
                ->orderByDesc('id')
                ->first();

            if (!$last) {
                return false;
            }

            $last->delete();
            $this->syncPlayerStats($match, $setNumber);

            return true;
        });
    }

    public function getBoard(TournamentMatch $match, int $setNumber): array
    {
        $state = $this->computeMatchState($match);
        $setInfo = $state['sets'][$setNumber] ?? [
            'home' => 0, 'away' => 0, 'target' => $match->stage->setPoints(),
            'decided' => false, 'isDecidingSet' => false,
        ];

        $home = $setInfo['home'];
        $away = $setInfo['away'];
        $target = $setInfo['target'];
        $decided = $setInfo['decided'];

        $events = MatchRallyEvent::where('match_id', $match->id)->where('set_number', $setNumber)->get();

        $cellsHome = [];
        $cellsAway = [];
        foreach ($events as $ev) {
            $type = in_array($ev->action_type, MatchRallyEvent::SELF_ACTIONS, true)
                ? 'player'
                : ($ev->action_type === MatchRallyEvent::ACTION_UNATTRIBUTED ? 'unattributed' : 'opp_error');
            $cell = ['type' => $type, 'player_id' => $ev->player_id, 'action_type' => $ev->action_type];
            if ((int) $ev->team_id === (int) $match->team_home_id) {
                $cellsHome[$ev->team_point_number] = $cell;
            } else {
                $cellsAway[$ev->team_point_number] = $cell;
            }
        }

        $players = $this->playerStatsService->getMatchPlayers($match);

        return [
            'set_number'      => $setNumber,
            'target_points'   => $target,
            'is_deciding_set' => $setInfo['isDecidingSet'] ?? false,
            'decided'         => $decided,
            'score'           => ['home' => $home, 'away' => $away],
            'can_undo'        => $events->isNotEmpty(),
            'columns'         => [
                'home' => $decided ? $home : max($target, $home + 1),
                'away' => $decided ? $away : max($target, $away + 1),
            ],
            'cells'           => ['home' => $cellsHome, 'away' => $cellsAway],
            'players'         => $players,
            'aggregates'      => [
                'home' => $this->aggregateFromEvents($events, (int) $match->team_home_id, $players['home']),
                'away' => $this->aggregateFromEvents($events, (int) $match->team_away_id, $players['away']),
            ],
        ];
    }

    public function getActiveSetNumber(TournamentMatch $match): int
    {
        $state = $this->computeMatchState($match);
        return empty($state['sets']) ? 1 : array_key_last($state['sets']);
    }

    public function isMatchReadyToFinalize(TournamentMatch $match): bool
    {
        return $this->computeMatchState($match)['matchDecided'];
    }

    /** @return array<int, array{0:int,1:int}> [[home,away], ...] по сыгранным сетам */
    public function buildFinalSets(TournamentMatch $match): array
    {
        $state = $this->computeMatchState($match);
        $sets = [];
        foreach ($state['sets'] as $info) {
            $sets[] = [$info['home'], $info['away']];
        }
        return $sets;
    }

    /**
     * Последовательно проходит сеты матча по логу очков, определяя для каждого
     * целевые очки (обычный/решающий сет — та же формула, что и в
     * TournamentMatchService::validateScore(), осознанно продублирована для
     * поточкового режима), текущий счёт и завершённость.
     */
    public function computeMatchState(TournamentMatch $match): array
    {
        $stage = $match->stage;
        $info = $this->setsInfo($stage->matchFormat());
        $maxSets = $info['max'];
        $setsToWin = $info['toWin'];

        $homeWins = 0;
        $awayWins = 0;
        $sets = [];

        for ($setNumber = 1; $setNumber <= $maxSets; $setNumber++) {
            if ($homeWins === $setsToWin || $awayWins === $setsToWin) {
                break;
            }

            $isDecidingSet = $maxSets > 1
                && (($setNumber === $maxSets) || ($homeWins === $setsToWin - 1 && $awayWins === $setsToWin - 1));
            $target = $isDecidingSet ? $stage->decidingSetPoints() : $stage->setPoints();

            [$home, $away] = $this->setScore($match, $setNumber);
            $decided = $this->isSetDecided($home, $away, $target);

            $sets[$setNumber] = [
                'home' => $home, 'away' => $away, 'target' => $target,
                'decided' => $decided, 'isDecidingSet' => $isDecidingSet,
            ];

            if (!$decided) {
                break;
            }

            if ($home > $away) {
                $homeWins++;
            } else {
                $awayWins++;
            }
        }

        return [
            'sets'         => $sets,
            'homeWins'     => $homeWins,
            'awayWins'     => $awayWins,
            'matchDecided' => $homeWins === $setsToWin || $awayWins === $setsToWin,
            'maxSets'      => $maxSets,
            'setsToWin'    => $setsToWin,
        ];
    }

    protected function syncPlayerStats(TournamentMatch $match, int $setNumber): void
    {
        $players = $this->playerStatsService->getMatchPlayers($match);
        $roster = [
            (int) $match->team_home_id => $players['home'],
            (int) $match->team_away_id => $players['away'],
        ];

        $setEvents = MatchRallyEvent::where('match_id', $match->id)->where('set_number', $setNumber)->get();
        $this->upsertAggregates($match, $setNumber, $setEvents, $roster);

        // Агрегат за весь матч (set_number=0) — сумма по всем сетам
        $allEvents = MatchRallyEvent::where('match_id', $match->id)->get();
        $this->upsertAggregates($match, 0, $allEvents, $roster);
    }

    /**
     * @param array<int, array<int, array{id:int,name:string}>> $roster team_id => confirmed игроки
     */
    private function upsertAggregates(TournamentMatch $match, int $setNumber, $events, array $roster): void
    {
        foreach ($roster as $teamId => $teamPlayers) {
            foreach ($teamPlayers as $p) {
                $userId = $p['id'];

                // serves_total/attacks_total рали-режимом не отслеживаются (считаются
                // только очко-образующие действия) — сохраняем то, что уже было
                // введено вручную на странице /player-stats, если было.
                $existing = MatchPlayerStats::where('match_id', $match->id)
                    ->where('set_number', $setNumber)
                    ->where('user_id', $userId)
                    ->first();

                $data = [
                    'serves_total'  => $existing->serves_total ?? 0,
                    'attacks_total' => $existing->attacks_total ?? 0,
                    'aces' => 0, 'serve_errors' => 0, 'kills' => 0, 'attack_errors' => 0,
                    'blocks' => 0, 'block_errors' => 0, 'digs' => 0, 'reception_errors' => 0, 'assists' => 0,
                ];

                foreach ($events as $ev) {
                    if ((int) $ev->stat_team_id === $teamId && (int) $ev->player_id === $userId) {
                        $field = MatchRallyEvent::ACTION_STAT_FIELD[$ev->action_type] ?? null;
                        if ($field) {
                            $data[$field]++;
                        }
                    }
                    if ((int) $ev->team_id === $teamId) {
                        if ((int) $ev->dig_user_id === $userId) {
                            $data['digs']++;
                        }
                        if ((int) $ev->assist_user_id === $userId) {
                            $data['assists']++;
                        }
                    }
                }

                $this->playerStatsService->saveSetStats($match, $setNumber, $userId, $teamId, $data);
            }
        }
    }

    /**
     * @param array<int, array{id:int,name:string}> $rosterPlayers
     */
    private function aggregateFromEvents($events, int $teamId, array $rosterPlayers): array
    {
        $result = [];
        foreach ($rosterPlayers as $p) {
            $result[$p['id']] = array_fill_keys(PlayerMatchStatsService::STAT_FIELDS, 0);
            $result[$p['id']]['points_scored'] = 0;
            $result[$p['id']]['user_name'] = $p['name'];
        }

        foreach ($events as $ev) {
            if ((int) $ev->stat_team_id === $teamId && $ev->player_id && isset($result[$ev->player_id])) {
                $field = MatchRallyEvent::ACTION_STAT_FIELD[$ev->action_type] ?? null;
                if ($field) {
                    $result[$ev->player_id][$field]++;
                }
            }
            if ((int) $ev->team_id === $teamId) {
                if ($ev->dig_user_id && isset($result[$ev->dig_user_id])) {
                    $result[$ev->dig_user_id]['digs']++;
                }
                if ($ev->assist_user_id && isset($result[$ev->assist_user_id])) {
                    $result[$ev->assist_user_id]['assists']++;
                }
            }
        }

        foreach ($result as &$r) {
            $r['points_scored'] = $r['aces'] + $r['kills'] + $r['blocks'];
        }

        return $result;
    }

    private function setsInfo(string $format): array
    {
        return match ($format) {
            'bo1'   => ['max' => 1, 'toWin' => 1],
            'bo3'   => ['max' => 3, 'toWin' => 2],
            'bo5'   => ['max' => 5, 'toWin' => 3],
            default => ['max' => 3, 'toWin' => 2],
        };
    }

    /** @return array{0:int,1:int} */
    private function setScore(TournamentMatch $match, int $setNumber): array
    {
        $home = MatchRallyEvent::where('match_id', $match->id)
            ->where('set_number', $setNumber)->where('team_id', $match->team_home_id)->count();
        $away = MatchRallyEvent::where('match_id', $match->id)
            ->where('set_number', $setNumber)->where('team_id', $match->team_away_id)->count();
        return [$home, $away];
    }
}
