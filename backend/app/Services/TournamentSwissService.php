<?php

namespace App\Services;

use App\Models\TournamentStage;
use App\Models\TournamentMatch;
use App\Models\TournamentStanding;
use App\Models\TournamentGroup;
use App\Models\TournamentGroupTeam;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TournamentSwissService
{
    /**
     * Сгенерировать пары для следующего тура швейцарской системы.
     *
     * Правила:
     * - Команды с одинаковым кол-вом очков играют друг с другом
     * - Запрет повторных встреч
     * - Если невозможно — берём ближайших по очкам
     *
     * @return Collection<TournamentMatch>
     */
    public function generateNextRound(TournamentStage $stage): Collection
    {
        $currentRound = ($stage->matches()->max('round') ?? 0) + 1;
        $maxRounds = (int) $stage->cfg('rounds_count', 5);

        if ($currentRound > $maxRounds) {
            // Туры исчерпаны — не ошибка конфигурации, а нормальное завершение
            // формата (контракт как у King of the Court::generateNextMatch()).
            return collect();
        }

        $groupId = $stage->cfg('swiss_group_id');

        // Получаем standings отсортированные по очкам
        $standings = TournamentStanding::where('stage_id', $stage->id)
            ->orderByDesc('rating_points')
            ->orderByDesc(DB::raw('sets_won - sets_lost'))
            ->orderByDesc(DB::raw('points_scored - points_conceded'))
            ->get();

        if ($standings->count() < 2) {
            throw new \InvalidArgumentException('Недостаточно команд для тура.');
        }

        // Все прошлые встречи (чтобы запретить повторы)
        $playedPairs = $this->getPlayedPairs($stage);

        // Подбираем пары
        $pairs = $this->matchTeams($standings, $playedPairs);

        return DB::transaction(function () use ($stage, $pairs, $currentRound, $groupId) {
            $matches = collect();
            $matchNum = ($stage->matches()->max('match_number') ?? 0) + 1;

            foreach ($pairs as [$homeId, $awayId]) {
                $match = TournamentMatch::create([
                    'stage_id'     => $stage->id,
                    'group_id'     => $groupId,
                    'round'        => $currentRound,
                    'match_number' => $matchNum++,
                    'team_home_id' => $homeId,
                    'team_away_id' => $awayId,
                    'status'       => TournamentMatch::STATUS_SCHEDULED,
                ]);
                $matches->push($match);
            }

            return $matches;
        });
    }

    /**
     * Подбор пар: жадный алгоритм сверху вниз.
     *
     * @param  Collection<TournamentStanding> $standings
     * @param  array<string, bool>            $playedPairs  "min-max" => true
     * @return array<array{int, int}>
     */
    private function matchTeams(Collection $standings, array $playedPairs): array
    {
        $teamIds = $standings->pluck('team_id')->toArray();
        $n = count($teamIds);
        $used = [];
        $pairs = [];

        for ($i = 0; $i < $n; $i++) {
            if (isset($used[$teamIds[$i]])) continue;

            $home = $teamIds[$i];
            $paired = false;

            // Ищем ближайшего непарного соперника, с которым ещё не играли
            for ($j = $i + 1; $j < $n; $j++) {
                if (isset($used[$teamIds[$j]])) continue;

                $away = $teamIds[$j];
                $pairKey = min($home, $away) . '-' . max($home, $away);

                if (!isset($playedPairs[$pairKey])) {
                    $pairs[] = [$home, $away];
                    $used[$home] = true;
                    $used[$away] = true;
                    $paired = true;
                    break;
                }
            }

            // Если не нашли без повтора — берём первого свободного (допускаем повтор)
            if (!$paired) {
                for ($j = $i + 1; $j < $n; $j++) {
                    if (isset($used[$teamIds[$j]])) continue;
                    $away = $teamIds[$j];
                    $pairs[] = [$home, $away];
                    $used[$home] = true;
                    $used[$away] = true;
                    break;
                }
            }
        }

        return $pairs;
    }

    /**
     * Получить все сыгранные пары для стадии.
     *
     * @return array<string, bool>  "min_id-max_id" => true
     */
    private function getPlayedPairs(TournamentStage $stage): array
    {
        $matches = $stage->matches()
            ->whereNotNull('team_home_id')
            ->whereNotNull('team_away_id')
            ->get(['team_home_id', 'team_away_id']);

        $pairs = [];
        foreach ($matches as $m) {
            $key = min($m->team_home_id, $m->team_away_id) . '-' . max($m->team_home_id, $m->team_away_id);
            $pairs[$key] = true;
        }

        return $pairs;
    }

    /**
     * Инициализация швейцарской системы: создаём standings и первый тур.
     *
     * @param  int[] $teamIds
     */
    public function initialize(TournamentStage $stage, array $teamIds): Collection
    {
        // Обёрточная TournamentGroup — без неё standings/matches никогда не
        // попадут в рендер $group->standings в setup.blade.php (та рендерит
        // турнирную таблицу только через $stage->groups) и recalculateGroup()
        // никогда не вызовется из submitScore() (тот триггерится по
        // $match->group_id). Тот же паттерн, что и у King of the Court.
        $group = TournamentGroup::create([
            'stage_id'   => $stage->id,
            'name'       => 'Швейцарская система',
            'sort_order' => 1,
        ]);

        // Создаём standings для всех команд
        foreach ($teamIds as $teamId) {
            TournamentStanding::firstOrCreate([
                'stage_id' => $stage->id,
                'group_id' => $group->id,
                'team_id'  => $teamId,
            ]);
        }

        // rounds_count — сколько туров сыграть всего. Организатор задаёт при
        // создании стадии (форма setup.blade.php); если не задал — дефолт
        // ceil(log2(команд)), минимум 3 (стандартная эвристика числа туров
        // швейцарки для полного разделения по очкам). count($teamIds) >= 2
        // гарантирован вызывающей стороной (TournamentController::draw()
        // отсекает draw() при < 2 подтверждённых команд ДО initialize()).
        $config = $stage->config ?? [];
        $roundsCount = (int) ($config['rounds_count'] ?? 0);
        if ($roundsCount < 1) {
            $roundsCount = max(3, (int) ceil(log(count($teamIds), 2)));
        }

        $stage->update([
            'status' => TournamentStage::STATUS_IN_PROGRESS,
            'config' => array_merge($config, [
                'swiss_group_id' => $group->id,
                'rounds_count'   => $roundsCount,
            ]),
        ]);

        return $this->generateNextRound($stage->fresh());
    }
}
