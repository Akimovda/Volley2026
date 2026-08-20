<?php

namespace App\Services;

use App\Models\TournamentStage;
use App\Models\TournamentMatch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TournamentBracketService
{
    /**
     * Генерация single elimination bracket.
     *
     * Принимает список team_ids (уже посеянных/отсортированных).
     * Если количество не степень двойки — добавляет BYE (null teams),
     * и автоматически проводит BYE-матчи.
     *
     * @param  int[]  $teamIds  Отсортированные по посеву
     * @param  bool   $thirdPlaceMatch  Матч за 3-е место
     * @return Collection<TournamentMatch>
     */
    public function generateSingleElimination(
        TournamentStage $stage,
        array $teamIds,
        bool $thirdPlaceMatch = false,
    ): Collection {
        $count = count($teamIds);
        if ($count < 2) {
            throw new \InvalidArgumentException('Нужно минимум 2 команды для сетки.');
        }

        // Расширяем до ближайшей степени двойки
        $bracketSize = 1;
        while ($bracketSize < $count) {
            $bracketSize *= 2;
        }

        // Заполняем BYE
        $seeded = $this->seedBracket($teamIds, $bracketSize);

        return DB::transaction(function () use ($stage, $seeded, $bracketSize, $thirdPlaceMatch) {
            $totalRounds = (int) log($bracketSize, 2);
            $allMatches = collect();

            // Создаём матчи по раундам (от финала к первому раунду)
            // чтобы правильно связать next_match_id
            $matchesByRound = [];

            // Сначала создаём все матчи без связей
            $matchNumber = 1;

            for ($round = 1; $round <= $totalRounds; $round++) {
                $matchesInRound = $bracketSize / pow(2, $round);
                $matchesByRound[$round] = [];

                for ($i = 0; $i < $matchesInRound; $i++) {
                    $match = TournamentMatch::create([
                        'stage_id'     => $stage->id,
                        'round'        => $round,
                        'match_number' => $matchNumber++,
                        'status'       => TournamentMatch::STATUS_SCHEDULED,
                    ]);
                    $matchesByRound[$round][] = $match;
                    $allMatches->push($match);
                }
            }

            // Матч за 3-е место (опционально)
            $thirdPlace = null;
            if ($thirdPlaceMatch && $totalRounds >= 2) {
                $thirdPlace = TournamentMatch::create([
                    'stage_id'     => $stage->id,
                    'round'        => $totalRounds, // тот же раунд что финал
                    'match_number' => $matchNumber++,
                    'status'       => TournamentMatch::STATUS_SCHEDULED,
                    'court'        => '3rd place',
                ]);
                $allMatches->push($thirdPlace);
            }

            // Связываем next_match_id / next_match_slot
            for ($round = 1; $round < $totalRounds; $round++) {
                $currentMatches = $matchesByRound[$round];
                $nextMatches = $matchesByRound[$round + 1];

                foreach ($currentMatches as $i => $match) {
                    $nextMatchIndex = intdiv($i, 2);
                    $slot = ($i % 2 === 0) ? 'home' : 'away';

                    $updates = [
                        'next_match_id'   => $nextMatches[$nextMatchIndex]->id,
                        'next_match_slot' => $slot,
                    ];

                    // Полуфиналы → проигравшие идут на матч за 3-е место
                    if ($thirdPlace && $round === $totalRounds - 1) {
                        $loserSlot = ($i % 2 === 0) ? 'home' : 'away';
                        $updates['loser_next_match_id'] = $thirdPlace->id;
                        $updates['loser_next_match_slot'] = $loserSlot;
                    }

                    $match->update($updates);
                }
            }

            // Заполняем команды первого раунда
            $firstRound = $matchesByRound[1];
            foreach ($firstRound as $i => $match) {
                $homeIdx = $i * 2;
                $awayIdx = $i * 2 + 1;

                $match->update([
                    'team_home_id' => $seeded[$homeIdx] ?? null,
                    'team_away_id' => $seeded[$awayIdx] ?? null,
                ]);
            }

            // Авто-проводим BYE-матчи (где одна команда null)
            $this->resolveByes($matchesByRound[1]);

            return $allMatches;
        });
    }

    /**
     * Посев по стандартной bracket-схеме.
     * Seed 1 vs Seed N, Seed 2 vs Seed N-1, etc.
     * Расставляем так, чтобы сильнейшие встретились только в финале.
     *
     * @param  int[]  $teamIds  Отсортированные по силе (первый = сильнейший)
     * @param  int    $bracketSize  Степень двойки
     * @return array<int|null>  team_id или null для BYE
     */
    private function seedBracket(array $teamIds, int $bracketSize): array
    {
        // Стандартный алгоритм bracket seeding
        $positions = $this->generateBracketPositions($bracketSize);

        $seeded = array_fill(0, $bracketSize, null);
        foreach ($positions as $pos => $seed) {
            if ($seed <= count($teamIds)) {
                $seeded[$pos] = $teamIds[$seed - 1];
            }
            // else null = BYE
        }

        return $seeded;
    }

    /**
     * Генерирует позиции для посева: [0=>1, 1=>N, 2=>N/2+1, 3=>N/2, ...]
     * Стандартная рекурсивная bracket-раскладка.
     */
    private function generateBracketPositions(int $size): array
    {
        if ($size === 1) return [1];

        $half = $this->generateBracketPositions($size / 2);
        $result = [];

        foreach ($half as $seed) {
            $result[] = $seed;
            $result[] = $size + 1 - $seed;
        }

        return $result;
    }

    /**
     * Авто-продвижение для BYE-матчей первого раунда.
     */
    private function resolveByes(array $firstRoundMatches): void
    {
        foreach ($firstRoundMatches as $match) {
            $match->refresh();

            $homeNull = is_null($match->team_home_id);
            $awayNull = is_null($match->team_away_id);

            if ($homeNull && $awayNull) {
                // Обе BYE — пропускаем
                $match->update(['status' => TournamentMatch::STATUS_CANCELLED]);
                continue;
            }

            if ($homeNull || $awayNull) {
                // Одна BYE — автопобеда
                $winnerId = $homeNull ? $match->team_away_id : $match->team_home_id;

                $match->update([
                    'winner_team_id' => $winnerId,
                    'status'         => TournamentMatch::STATUS_COMPLETED,
                    'score_home'     => [],
                    'score_away'     => [],
                    'scored_at'      => now(),
                ]);

                // Propagate
                if ($match->next_match_id && $match->next_match_slot) {
                    $field = $match->next_match_slot === 'home' ? 'team_home_id' : 'team_away_id';
                    TournamentMatch::where('id', $match->next_match_id)
                        ->update([$field => $winnerId]);
                }
            }
        }
    }



    /**
     * Генерация double elimination bracket.
     *
     * Верхняя сетка (winners) + нижняя сетка (losers) + гранд-финал.
     *
     * @param  int[]  $teamIds  Отсортированные по посеву
     * @return Collection<TournamentMatch>
     */
    public function generateDoubleElimination(TournamentStage $stage, array $teamIds): Collection
    {
        $count = count($teamIds);
        if ($count < 4) {
            throw new \InvalidArgumentException('Нужно минимум 4 команды для double elimination.');
        }

        $bracketSize = 1;
        while ($bracketSize < $count) {
            $bracketSize *= 2;
        }

        $seeded = $this->seedBracket($teamIds, $bracketSize);

        return DB::transaction(function () use ($stage, $seeded, $bracketSize) {
            $allMatches = collect();
            $upperRounds = (int) log($bracketSize, 2);
            $matchNumber = 1;

            // === UPPER BRACKET ===
            $upperByRound = [];
            for ($round = 1; $round <= $upperRounds; $round++) {
                $matchesInRound = $bracketSize / pow(2, $round);
                $upperByRound[$round] = [];

                for ($i = 0; $i < $matchesInRound; $i++) {
                    $match = TournamentMatch::create([
                        'stage_id'        => $stage->id,
                        'round'           => $round,
                        'bracket_position' => 'upper',
                        'match_number'    => $matchNumber++,
                        'status'          => TournamentMatch::STATUS_SCHEDULED,
                    ]);
                    $upperByRound[$round][] = $match;
                    $allMatches->push($match);
                }
            }

            // === LOWER BRACKET ===
            // Нижняя сетка имеет (upperRounds - 1) * 2 раундов
            $lowerRounds = ($upperRounds - 1) * 2;
            $lowerByRound = [];

            $lowerTeamsCount = $bracketSize / 2; // начальная ёмкость нижней сетки

            for ($lr = 1; $lr <= $lowerRounds; $lr++) {
                // Нечётные раунды: приём проигравших из верхней сетки
                // Чётные раунды: матчи между собой
                $isDropRound = ($lr % 2 === 1);

                if ($isDropRound) {
                    $matchesInRound = intdiv($lowerTeamsCount, 2);
                } else {
                    $matchesInRound = intdiv($lowerTeamsCount, 2);
                    $lowerTeamsCount = $matchesInRound; // уменьшаем
                }

                // Упрощённая генерация: фиксированное кол-во матчей
                $actualMatches = max(1, intdiv($bracketSize, pow(2, intdiv($lr + 1, 2) + 1)));

                $lowerByRound[$lr] = [];
                for ($i = 0; $i < $actualMatches; $i++) {
                    $match = TournamentMatch::create([
                        'stage_id'        => $stage->id,
                        'round'           => $upperRounds + $lr,
                        'bracket_position' => 'lower',
                        'match_number'    => $matchNumber++,
                        'status'          => TournamentMatch::STATUS_SCHEDULED,
                    ]);
                    $lowerByRound[$lr][] = $match;
                    $allMatches->push($match);
                }
            }

            // === GRAND FINAL ===
            $grandFinal = TournamentMatch::create([
                'stage_id'        => $stage->id,
                'round'           => $upperRounds + $lowerRounds + 1,
                'bracket_position' => 'upper',
                'match_number'    => $matchNumber++,
                'status'          => TournamentMatch::STATUS_SCHEDULED,
                'court'           => 'Grand Final',
            ]);
            $allMatches->push($grandFinal);

            // === Связи upper bracket ===
            for ($round = 1; $round < $upperRounds; $round++) {
                foreach ($upperByRound[$round] as $i => $match) {
                    $nextIdx = intdiv($i, 2);
                    $slot = ($i % 2 === 0) ? 'home' : 'away';

                    $updates = [
                        'next_match_id'   => $upperByRound[$round + 1][$nextIdx]->id,
                        'next_match_slot' => $slot,
                    ];

                    // Проигравший → нижняя сетка
                    $lowerRoundIdx = ($round - 1) * 2 + 1; // нечётный раунд нижней
                    if (isset($lowerByRound[$lowerRoundIdx])) {
                        $loserIdx = min($i, count($lowerByRound[$lowerRoundIdx]) - 1);
                        if (isset($lowerByRound[$lowerRoundIdx][$loserIdx])) {
                            $loserSlot = ($i % 2 === 0) ? 'home' : 'away';
                            $updates['loser_next_match_id'] = $lowerByRound[$lowerRoundIdx][$loserIdx]->id;
                            $updates['loser_next_match_slot'] = $loserSlot;
                        }
                    }

                    $match->update($updates);
                }
            }

            // Финал upper → grand final
            if (isset($upperByRound[$upperRounds][0])) {
                $upperByRound[$upperRounds][0]->update([
                    'next_match_id'   => $grandFinal->id,
                    'next_match_slot' => 'home',
                    // no-reset double elim: проигравший UB-финала падает в LB-финал.
                    // home LB-финала уже занят победителем LB-полуфинала (цикл lower-связей
                    // ниже), поэтому проигравший UB-финала идёт в свободный away.
                    'loser_next_match_id'   => $lowerByRound[$lowerRounds][0]->id ?? null,
                    'loser_next_match_slot' => 'away',
                ]);
            }

            // Связи lower bracket между раундами
            for ($lr = 1; $lr < $lowerRounds; $lr++) {
                if (!isset($lowerByRound[$lr]) || !isset($lowerByRound[$lr + 1])) continue;
                foreach ($lowerByRound[$lr] as $i => $match) {
                    $nextIdx = min(intdiv($i, 2), count($lowerByRound[$lr + 1]) - 1);
                    if (isset($lowerByRound[$lr + 1][$nextIdx])) {
                        $slot = ($i % 2 === 0) ? 'home' : 'away';
                        $match->update([
                            'next_match_id'   => $lowerByRound[$lr + 1][$nextIdx]->id,
                            'next_match_slot' => $slot,
                        ]);
                    }
                }
            }

            // Финал lower → grand final
            $lastLowerRound = $lowerByRound[$lowerRounds] ?? [];
            if (!empty($lastLowerRound)) {
                $lastLowerRound[0]->update([
                    'next_match_id'   => $grandFinal->id,
                    'next_match_slot' => 'away',
                ]);
            }

            // === Заполняем первый раунд upper ===
            foreach ($upperByRound[1] as $i => $match) {
                $match->update([
                    'team_home_id' => $seeded[$i * 2] ?? null,
                    'team_away_id' => $seeded[$i * 2 + 1] ?? null,
                ]);
            }

            // BYE авто-проводим
            $this->resolveByes($upperByRound[1]);

            return $allMatches;
        });
    }

    /**
     * Продвижение команд из групповой стадии в стадию плей-офф.
     *
     * Идемпотентно/регенерируемо: в отличие от generateGroupCrossover()
     * (плоский список мест, можно дозаполнять), полная сетка либо
     * пересоздаётся целиком, либо не трогается — топ-ап невозможен, т.к.
     * next_match_id/loser_next_match_id связывают раунды между собой.
     * Если в стадии уже есть хоть один сыгранный (completed) матч — отказ,
     * нужен явный revert. Если все матчи ещё scheduled (например, повторная
     * генерация после ошибочного посева) — старые матчи удаляются и сетка
     * строится заново.
     *
     * @param  TournamentStage  $groupStage   Стадия с группами (completed)
     * @param  TournamentStage  $playoffStage  Стадия single_elim (pending)
     * @param  int              $advancePerGroup  Сколько команд выходит из каждой группы
     */
    public function advanceToPlayoff(
        TournamentStage $groupStage,
        TournamentStage $playoffStage,
        int $advancePerGroup,
        TournamentStandingsService $standingsService,
    ): Collection {
        if ($playoffStage->matches()->where('status', TournamentMatch::STATUS_COMPLETED)->exists()) {
            throw new \InvalidArgumentException('В стадии уже есть сыгранные матчи. Сначала откатите стадию, чтобы пересоздать сетку.');
        }
        $playoffStage->matches()->delete();

        $groups = $groupStage->groups()->orderBy('sort_order')->get();
        $advancing = [];

        foreach ($groups as $group) {
            $topTeams = $standingsService->getAdvancingTeams(
                $groupStage->id, $group->id, $advancePerGroup
            );
            foreach ($topTeams as $standing) {
                $advancing[] = $standing->team_id;
            }
        }

        // Кусок 3: добор лучших до полной сетки (практика FIVB — напр. 3 группы
        // → 6 прямых + 2 лучших третьих = сетка на 8). Не хватает до степени
        // двойки — добираем команды следующего непрошедшего ранга по всем
        // группам, отсортированные compareStrength (очки→сеты→мячи). Добор ВВЕРХ:
        // реально прошедшие не отсекаются; остаток закрывает BYE (крайний случай).
        $directCount = count($advancing);
        if ($directCount >= 2) {
            $bracketSize = 1;
            while ($bracketSize < $directCount) { $bracketSize *= 2; }
            $needed = $bracketSize - $directCount;
            if ($needed > 0) {
                $wildcardRank = $advancePerGroup + 1;
                $candidates = \App\Models\TournamentStanding::where('stage_id', $groupStage->id)
                    ->where('rank', $wildcardRank)
                    ->get()
                    ->sort(fn($a, $b) => $standingsService->compareStrength($a, $b))
                    ->values();
                foreach ($candidates->take($needed) as $standing) {
                    $advancing[] = $standing->team_id;
                }
            }
        }

        if (count($advancing) < 2) {
            throw new \InvalidArgumentException('Недостаточно команд для плей-офф.');
        }

        $thirdPlace = (bool) $playoffStage->cfg('third_place_match', false);

        $matches = $this->generateSingleElimination($playoffStage, $advancing, $thirdPlace);

        // Источник правды для TournamentStatsService::calculateFinalClassification() —
        // явный finals_mode в config, а не угадывание по названию корта/раунду
        // (см. report_402_finals_bug.md).
        $playoffStage->update(['config' => array_merge($playoffStage->config ?? [], ['finals_mode' => 'bracket'])]);

        return $matches;
    }

    /**
     * "Прямые матчи по местам" — альтернатива advanceToPlayoff() для РОВНО
     * 2 групп, когда группового этапа уже достаточно, чтобы считать его
     * полуфиналом: 1-е места групп играют напрямую за 1-2 место, 2-е места —
     * за 3-4 место, без дополнительного раунда полуфиналов между группами.
     * В отличие от generateSingleElimination() (полная сетка на bracketSize
     * участников, для 4 команд — ВСЕГДА 2 раунда), здесь ровно N матчей
     * (по числу учитываемых мест), все одновременно играбельны, без
     * next_match_id/loser_next_match_id связей — каждый матч сам по себе
     * определяет итоговое место, не продвигает никого дальше.
     *
     * @param  TournamentStage  $groupStage    Стадия с группами (completed), РОВНО 2 группы
     * @param  TournamentStage  $playoffStage  Стадия single_elim (pending ИЛИ уже completed —
     *                                         например, организатор изначально запросил
     *                                         только 2 места, а позже решил дозаполнить
     *                                         матч за 3-4 в ту же стадию)
     * @param  int              $placesCount   Сколько мест разыгрывать напрямую (2 = только за 1-2; 4 = ещё и за 3-4)
     *
     * Идемпотентно/инкрементально: если матч для какой-то пары рангов уже
     * существует в этой стадии (организатор раньше вызывал с меньшим
     * placesCount) — он НЕ пересоздаётся, создаются только недостающие ранги.
     * Это защищает от дубля матча "за 1-2" при повторном вызове с
     * placesCount=4 после того, как "за 1-2" уже был создан и сыгран.
     */
    public function generateGroupCrossover(
        TournamentStage $groupStage,
        TournamentStage $playoffStage,
        int $placesCount,
        TournamentStandingsService $standingsService,
    ): Collection {
        $groups = $groupStage->groups()->orderBy('sort_order')->get();

        if ($groups->count() !== 2) {
            throw new \InvalidArgumentException('Прямые матчи по местам доступны только для турниров ровно с 2 группами.');
        }

        $ranksNeeded = (int) ceil($placesCount / 2);
        $rankedA = $standingsService->getAdvancingTeams($groupStage->id, $groups[0]->id, $ranksNeeded);
        $rankedB = $standingsService->getAdvancingTeams($groupStage->id, $groups[1]->id, $ranksNeeded);

        if ($rankedA->count() < $ranksNeeded || $rankedB->count() < $ranksNeeded) {
            throw new \InvalidArgumentException('Недостаточно команд в группах для запрошенного числа мест.');
        }

        $existing = $playoffStage->matches()->get(['team_home_id', 'team_away_id']);
        $existsForPair = fn($homeId, $awayId) => $existing->contains(
            fn($m) => $m->team_home_id === $homeId && $m->team_away_id === $awayId
        );

        $matches = DB::transaction(function () use ($rankedA, $rankedB, $playoffStage, $ranksNeeded, $existsForPair) {
            $matches = collect();
            $nextMatchNumber = (int) ($playoffStage->matches()->max('match_number') ?? 0) + 1;

            for ($i = 0; $i < $ranksNeeded; $i++) {
                $homeId = $rankedA[$i]->team_id;
                $awayId = $rankedB[$i]->team_id;

                if ($existsForPair($homeId, $awayId)) {
                    continue;
                }

                $placeFrom = $i * 2 + 1;
                $placeTo   = $i * 2 + 2;
                $match = TournamentMatch::create([
                    'stage_id'     => $playoffStage->id,
                    'round'        => 1,
                    'match_number' => $nextMatchNumber++,
                    'team_home_id' => $homeId,
                    'team_away_id' => $awayId,
                    'status'       => TournamentMatch::STATUS_SCHEDULED,
                    'court'        => "Матч за {$placeFrom}-{$placeTo} место",
                ]);
                $matches->push($match);
            }
            return $matches;
        });

        // Источник правды для TournamentStatsService::calculateFinalClassification() —
        // явный finals_mode в config, а не угадывание по названию корта/раунду
        // (см. report_402_finals_bug.md).
        $playoffStage->update(['config' => array_merge($playoffStage->config ?? [], ['finals_mode' => 'placement'])]);

        return $matches;
    }
}
