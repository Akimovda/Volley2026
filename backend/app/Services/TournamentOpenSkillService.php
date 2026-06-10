<?php

namespace App\Services;

use App\Models\PlayerCareerStats;
use App\Models\TournamentMatch;
use App\Models\TournamentSeasonStats;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TournamentOpenSkillService
{
    const INITIAL_MU    = 25.0;
    const INITIAL_SIGMA = 8.333;
    const BETA          = 4.1667;  // skill noise (sigma / 2)
    const TAU           = 0.0833;  // dynamics (prevents sigma → 0)
    const MIN_SIGMA     = 0.5;

    // ---------------------------------------------------------------
    //  Public API
    // ---------------------------------------------------------------

    /**
     * Обновить рейтинги по итогам матча.
     *
     * @param array $winnerIds  user_id[] победившей команды
     * @param array $loserIds   user_id[] проигравшей команды
     * @param string $direction 'classic' | 'beach'
     * @param int|null $seasonId
     * @param int|null $leagueId
     */
    public function processMatchByIds(
        array $winnerIds,
        array $loserIds,
        string $direction,
        ?int $seasonId = null,
        ?int $leagueId = null
    ): void {
        if (empty($winnerIds) || empty($loserIds)) return;

        // --- Загружаем career stats ---
        $allIds = array_merge($winnerIds, $loserIds);
        $careerMap = PlayerCareerStats::whereIn('user_id', $allIds)
            ->where('direction', $direction)
            ->get()
            ->keyBy('user_id');

        // Создаём записи для игроков без career stats
        foreach ($allIds as $uid) {
            if (!$careerMap->has($uid)) {
                $cs = PlayerCareerStats::firstOrCreate(
                    ['user_id' => $uid, 'direction' => $direction],
                    ['mu' => self::INITIAL_MU, 'sigma' => self::INITIAL_SIGMA]
                );
                $careerMap->put($uid, $cs);
            }
        }

        $winnerRatings = $this->getRatings($winnerIds, $careerMap, 'mu', 'sigma');
        $loserRatings  = $this->getRatings($loserIds,  $careerMap, 'mu', 'sigma');

        [$newWinner, $newLoser] = $this->computeUpdate($winnerRatings, $loserRatings);

        // Сохраняем career stats
        $this->saveCareerRatings($winnerIds, $newWinner, $careerMap, $direction, 'mu', 'sigma');
        $this->saveCareerRatings($loserIds,  $newLoser,  $careerMap, $direction, 'mu', 'sigma');

        // --- Season stats ---
        if ($seasonId && $leagueId) {
            $seasonMap = TournamentSeasonStats::where('season_id', $seasonId)
                ->where('league_id', $leagueId)
                ->whereIn('user_id', $allIds)
                ->get()
                ->keyBy('user_id');

            foreach ($allIds as $uid) {
                if (!$seasonMap->has($uid)) {
                    $ss = TournamentSeasonStats::where('season_id', $seasonId)
                        ->where('league_id', $leagueId)
                        ->where('user_id', $uid)
                        ->first();
                    if ($ss) $seasonMap->put($uid, $ss);
                }
            }

            $winnerSeasonRatings = $this->getRatings($winnerIds, $seasonMap, 'mu_season', 'sigma_season');
            $loserSeasonRatings  = $this->getRatings($loserIds,  $seasonMap, 'mu_season', 'sigma_season');

            [$newWinnerSeason, $newLoserSeason] = $this->computeUpdate($winnerSeasonRatings, $loserSeasonRatings);

            $this->saveSeasonRatings($winnerIds, $newWinnerSeason, $seasonMap, $seasonId, $leagueId);
            $this->saveSeasonRatings($loserIds,  $newLoserSeason,  $seasonMap, $seasonId, $leagueId);
        }
    }

    /**
     * Conservative Rating — публичный рейтинг.
     */
    public function conservativeRating(float $mu, float $sigma): float
    {
        return max(0.0, $mu - 3.0 * $sigma);
    }

    /**
     * Полный пересчёт с нуля по всем историческим матчам.
     */
    public function rebuildAll(): void
    {
        Log::info('[OpenSkill] Starting full rebuild...');

        // Сброс
        PlayerCareerStats::query()->update(['mu' => self::INITIAL_MU, 'sigma' => self::INITIAL_SIGMA]);
        TournamentSeasonStats::query()->update(['mu_season' => self::INITIAL_MU, 'sigma_season' => self::INITIAL_SIGMA]);

        $matches = TournamentMatch::with('stage.event')
            ->where('status', TournamentMatch::STATUS_COMPLETED)
            ->whereNotNull('winner_team_id')
            ->orderBy(DB::raw('COALESCE(scored_at, created_at)'))
            ->get();

        $processed = 0;

        foreach ($matches as $match) {
            $event = $match->stage?->event;
            if (!$event) continue;

            $direction = $event->direction ?? 'beach';

            $homeWon = $match->winner_team_id === $match->team_home_id;
            $winnerTeamId = $homeWon ? $match->team_home_id : $match->team_away_id;
            $loserTeamId  = $homeWon ? $match->team_away_id : $match->team_home_id;

            $winnerIds = $this->getTeamPlayerIds($winnerTeamId);
            $loserIds  = $this->getTeamPlayerIds($loserTeamId);

            // league_id для season stats
            $seasonId  = $event->season_id;
            $leagueId  = null;
            if ($seasonId) {
                $leagueId = DB::table('tournament_season_events')
                    ->where('event_id', $event->id)
                    ->value('league_id');
                if (!$leagueId) {
                    $leagueId = DB::table('tournament_leagues')
                        ->where('season_id', $seasonId)
                        ->value('id');
                }
            }

            $this->processMatchByIds($winnerIds, $loserIds, $direction, $seasonId, $leagueId);
            $processed++;
        }

        Log::info("[OpenSkill] Rebuilt {$processed} matches.");
    }

    // ---------------------------------------------------------------
    //  Core algorithm
    // ---------------------------------------------------------------

    /**
     * Вычислить новые mu/sigma для двух команд.
     *
     * @param array $winnerRatings [ [mu, sigma], ... ]
     * @param array $loserRatings  [ [mu, sigma], ... ]
     * @return array [ $newWinner, $newLoser ]  — массивы [mu, sigma] в том же порядке
     */
    private function computeUpdate(array $winnerRatings, array $loserRatings): array
    {
        $muW = array_sum(array_column($winnerRatings, 'mu'));
        $muL = array_sum(array_column($loserRatings, 'mu'));

        $sigmaWsq = array_sum(array_map(fn($r) => $r['sigma'] ** 2, $winnerRatings));
        $sigmaLsq = array_sum(array_map(fn($r) => $r['sigma'] ** 2, $loserRatings));

        $cSq = 2 * self::BETA ** 2 + $sigmaWsq + $sigmaLsq;
        $c   = sqrt($cSq);

        $t = ($muW - $muL) / $c;
        $v = $this->vFunc($t);
        $w = $this->wFunc($t, $v);

        $newWinner = [];
        foreach ($winnerRatings as $r) {
            $rankFactor = $r['sigma'] ** 2 / $c;
            $mu_new     = $r['mu'] + $rankFactor * $v;
            $varFactor  = ($r['sigma'] ** 2 / $cSq) * $w;
            $sigma_new  = sqrt(max(self::MIN_SIGMA ** 2, $r['sigma'] ** 2 * (1 - $varFactor) + self::TAU ** 2));
            $newWinner[] = ['mu' => round($mu_new, 4), 'sigma' => round($sigma_new, 4)];
        }

        $newLoser = [];
        foreach ($loserRatings as $r) {
            $rankFactor = $r['sigma'] ** 2 / $c;
            $mu_new     = $r['mu'] - $rankFactor * $v;
            $varFactor  = ($r['sigma'] ** 2 / $cSq) * $w;
            $sigma_new  = sqrt(max(self::MIN_SIGMA ** 2, $r['sigma'] ** 2 * (1 - $varFactor) + self::TAU ** 2));
            $newLoser[] = ['mu' => round($mu_new, 4), 'sigma' => round($sigma_new, 4)];
        }

        return [$newWinner, $newLoser];
    }

    // v(t) = phi(t) / Phi(t)
    private function vFunc(float $t): float
    {
        $cdf = $this->normalCdf($t);
        if ($cdf < 1e-10) return 10.0; // защита от деления на 0
        return $this->normalPdf($t) / $cdf;
    }

    // w(t) = v(t) * (v(t) + t)
    private function wFunc(float $t, float $v): float
    {
        return $v * ($v + $t);
    }

    // PDF стандартного нормального распределения
    private function normalPdf(float $x): float
    {
        return exp(-0.5 * $x * $x) / sqrt(2.0 * M_PI);
    }

    // CDF стандартного нормального распределения (Abramowitz & Stegun, max error 7.5e-8)
    private function normalCdf(float $x): float
    {
        $t = 1.0 / (1.0 + 0.2316419 * abs($x));
        $d = 0.3989422820 * exp(-0.5 * $x * $x);
        $poly = $t * (0.3193815 + $t * (-0.3565638 + $t * (1.7814779 + $t * (-1.8212560 + $t * 1.3302744))));
        $p = 1.0 - $d * $poly;
        return $x >= 0 ? $p : 1.0 - $p;
    }

    // ---------------------------------------------------------------
    //  Helpers
    // ---------------------------------------------------------------

    private function getTeamPlayerIds(int $teamId): array
    {
        return DB::table('event_team_members')
            ->where('event_team_id', $teamId)
            ->where('confirmation_status', 'confirmed')
            ->pluck('user_id')
            ->toArray();
    }

    private function getRatings(array $userIds, $modelMap, string $muField, string $sigmaField): array
    {
        return array_map(function ($uid) use ($modelMap, $muField, $sigmaField) {
            $record = $modelMap->get($uid);
            return [
                'mu'    => (float) ($record?->$muField    ?? self::INITIAL_MU),
                'sigma' => (float) ($record?->$sigmaField ?? self::INITIAL_SIGMA),
            ];
        }, $userIds);
    }

    private function saveCareerRatings(
        array $userIds,
        array $newRatings,
        $careerMap,
        string $direction,
        string $muField,
        string $sigmaField
    ): void {
        foreach ($userIds as $idx => $uid) {
            $cs = $careerMap->get($uid);
            if (!$cs) continue;
            $cs->$muField    = $newRatings[$idx]['mu'];
            $cs->$sigmaField = $newRatings[$idx]['sigma'];
            $cs->save();
        }
    }

    private function saveSeasonRatings(
        array $userIds,
        array $newRatings,
        $seasonMap,
        int $seasonId,
        int $leagueId
    ): void {
        foreach ($userIds as $idx => $uid) {
            $ss = $seasonMap->get($uid);
            if (!$ss) continue;
            $ss->mu_season    = $newRatings[$idx]['mu'];
            $ss->sigma_season = $newRatings[$idx]['sigma'];
            $ss->save();
        }
    }
}
