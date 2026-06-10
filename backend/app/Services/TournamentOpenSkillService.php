<?php

namespace App\Services;

use App\Models\PlayerCareerStats;
use App\Models\TournamentMatch;
use App\Models\TournamentSeasonStats;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TournamentOpenSkillService
{
    const INITIAL_MU    = 25.0;
    const INITIAL_SIGMA = 8.333;
    const BETA          = 4.1667;
    const TAU           = 0.0833;
    const MIN_SIGMA     = 0.5;

    // ---------------------------------------------------------------
    //  Public API
    // ---------------------------------------------------------------

    /**
     * Обновить рейтинги по итогам матча.
     */
    public function processMatchByIds(
        array $winnerIds,
        array $loserIds,
        string $direction,
        ?int $seasonId = null,
        ?int $leagueId = null,
        ?int $eventId  = null,
        ?int $matchId  = null
    ): void {
        if (empty($winnerIds) || empty($loserIds)) return;

        $allIds    = array_unique(array_merge($winnerIds, $loserIds));
        $careerMap = PlayerCareerStats::whereIn('user_id', $allIds)
            ->where('direction', $direction)
            ->get()
            ->keyBy('user_id');

        foreach ($allIds as $uid) {
            if (!$careerMap->has($uid)) {
                $cs = PlayerCareerStats::firstOrCreate(
                    ['user_id' => $uid, 'direction' => $direction],
                    ['mu' => self::INITIAL_MU, 'sigma' => self::INITIAL_SIGMA, 'mu_peak' => self::INITIAL_MU]
                );
                $careerMap->put($uid, $cs);
            }
        }

        // --- Вычисляем новые рейтинги ---
        $winnerRatings = $this->getRatings($winnerIds, $careerMap, 'mu', 'sigma');
        $loserRatings  = $this->getRatings($loserIds,  $careerMap, 'mu', 'sigma');

        [$newWinner, $newLoser] = $this->computeUpdate($winnerRatings, $loserRatings);

        // --- Записываем историю + обновляем пик (до save) ---
        $this->recordHistoryAndPeak($winnerIds, $winnerRatings, $newWinner, $careerMap, $eventId, $matchId);
        $this->recordHistoryAndPeak($loserIds,  $loserRatings,  $newLoser,  $careerMap, $eventId, $matchId);

        // --- Сохраняем career stats (mu, sigma, mu_peak, mu_peak_date) ---
        $this->saveCareerRatings($winnerIds, $newWinner, $careerMap);
        $this->saveCareerRatings($loserIds,  $newLoser,  $careerMap);

        // --- Статистика пар ---
        $gameScheme = $eventId
            ? DB::table('event_tournament_settings')->where('event_id', $eventId)->value('game_scheme')
            : null;
        $this->updatePairStats($winnerIds, true,  $direction, $gameScheme);
        $this->updatePairStats($loserIds,  false, $direction, $gameScheme);

        // --- Статистика соперников ---
        $this->updateOpponentStats($winnerIds, $loserIds);

        // --- Пересчёт производных метрик ---
        foreach ($allIds as $uid) {
            $this->recalcDerivedStats($uid, $direction, $careerMap->get($uid));
        }

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

            $winnerSeason = $this->getRatings($winnerIds, $seasonMap, 'mu_season', 'sigma_season');
            $loserSeason  = $this->getRatings($loserIds,  $seasonMap, 'mu_season', 'sigma_season');

            [$newWinnerSeason, $newLoserSeason] = $this->computeUpdate($winnerSeason, $loserSeason);

            $this->saveSeasonRatings($winnerIds, $newWinnerSeason, $seasonMap, $seasonId, $leagueId);
            $this->saveSeasonRatings($loserIds,  $newLoserSeason,  $seasonMap, $seasonId, $leagueId);
        }
    }

    public function conservativeRating(float $mu, float $sigma): float
    {
        return max(0.0, $mu - 3.0 * $sigma);
    }

    /**
     * Полный пересчёт с нуля по всей истории матчей.
     */
    public function rebuildAll(): void
    {
        Log::info('[OpenSkill] Starting full rebuild...');

        // Сброс — все таблицы с нуля
        DB::table('player_rating_history')->truncate();
        DB::table('player_pair_stats')->truncate();
        DB::table('player_opponent_stats')->truncate();

        PlayerCareerStats::query()->update([
            'mu'                => self::INITIAL_MU,
            'sigma'             => self::INITIAL_SIGMA,
            'mu_peak'           => self::INITIAL_MU,
            'mu_peak_date'      => null,
            'unique_opponents'  => 0,
            'unique_partners'   => 0,
            'main_partner_id'   => null,
            'main_partner_games'=> 0,
            'pair_stability'    => 0,
            'last_5_form'       => null,
            'last_10_form'      => null,
            'points_ratio'      => 1.0,
        ]);

        TournamentSeasonStats::query()->update([
            'mu_season'    => self::INITIAL_MU,
            'sigma_season' => self::INITIAL_SIGMA,
        ]);

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
            $homeWon   = $match->winner_team_id === $match->team_home_id;

            $winnerTeamId = $homeWon ? $match->team_home_id : $match->team_away_id;
            $loserTeamId  = $homeWon ? $match->team_away_id : $match->team_home_id;

            $winnerIds = $this->getTeamPlayerIds($winnerTeamId);
            $loserIds  = $this->getTeamPlayerIds($loserTeamId);

            $seasonId = $event->season_id;
            $leagueId = null;
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

            $this->processMatchByIds(
                $winnerIds, $loserIds, $direction,
                $seasonId, $leagueId, $event->id, $match->id
            );
            $processed++;
        }

        Log::info("[OpenSkill] Rebuilt {$processed} matches.");
    }

    // ---------------------------------------------------------------
    //  Core algorithm
    // ---------------------------------------------------------------

    private function computeUpdate(array $winnerRatings, array $loserRatings): array
    {
        $muW      = array_sum(array_column($winnerRatings, 'mu'));
        $muL      = array_sum(array_column($loserRatings,  'mu'));
        $sigmaWsq = array_sum(array_map(fn($r) => $r['sigma'] ** 2, $winnerRatings));
        $sigmaLsq = array_sum(array_map(fn($r) => $r['sigma'] ** 2, $loserRatings));

        $cSq = 2 * self::BETA ** 2 + $sigmaWsq + $sigmaLsq;
        $c   = sqrt($cSq);
        $t   = ($muW - $muL) / $c;
        $v   = $this->vFunc($t);
        $w   = $this->wFunc($t, $v);

        $newWinner = [];
        foreach ($winnerRatings as $r) {
            $rf  = $r['sigma'] ** 2 / $c;
            $vf  = ($r['sigma'] ** 2 / $cSq) * $w;
            $newWinner[] = [
                'mu'    => round($r['mu'] + $rf * $v, 4),
                'sigma' => round(sqrt(max(self::MIN_SIGMA ** 2, $r['sigma'] ** 2 * (1 - $vf) + self::TAU ** 2)), 4),
            ];
        }

        $newLoser = [];
        foreach ($loserRatings as $r) {
            $rf  = $r['sigma'] ** 2 / $c;
            $vf  = ($r['sigma'] ** 2 / $cSq) * $w;
            $newLoser[] = [
                'mu'    => round($r['mu'] - $rf * $v, 4),
                'sigma' => round(sqrt(max(self::MIN_SIGMA ** 2, $r['sigma'] ** 2 * (1 - $vf) + self::TAU ** 2)), 4),
            ];
        }

        return [$newWinner, $newLoser];
    }

    private function vFunc(float $t): float
    {
        $cdf = $this->normalCdf($t);
        return $cdf < 1e-10 ? 10.0 : $this->normalPdf($t) / $cdf;
    }

    private function wFunc(float $t, float $v): float { return $v * ($v + $t); }

    private function normalPdf(float $x): float
    {
        return exp(-0.5 * $x * $x) / sqrt(2.0 * M_PI);
    }

    private function normalCdf(float $x): float
    {
        $t = 1.0 / (1.0 + 0.2316419 * abs($x));
        $d = 0.3989422820 * exp(-0.5 * $x * $x);
        $p = 1.0 - $d * $t * (0.3193815 + $t * (-0.3565638 + $t * (1.7814779 + $t * (-1.8212560 + $t * 1.3302744))));
        return $x >= 0 ? $p : 1.0 - $p;
    }

    // ---------------------------------------------------------------
    //  История и пик
    // ---------------------------------------------------------------

    private function recordHistoryAndPeak(
        array $userIds,
        array $oldRatings,
        array $newRatings,
        $careerMap,
        ?int $eventId,
        ?int $matchId
    ): void {
        $now = now();

        foreach ($userIds as $idx => $uid) {
            $old = $oldRatings[$idx];
            $new = $newRatings[$idx];

            DB::table('player_rating_history')->insert([
                'user_id'      => $uid,
                'event_id'     => $eventId,
                'match_id'     => $matchId,
                'mu_before'    => $old['mu'],
                'mu_after'     => $new['mu'],
                'sigma_before' => $old['sigma'],
                'sigma_after'  => $new['sigma'],
                'recorded_at'  => $now,
                'created_at'   => $now,
            ]);

            // Обновляем пик на объекте (сохранится в saveCareerRatings)
            $cs = $careerMap->get($uid);
            if ($cs && $new['mu'] > ($cs->mu_peak ?? self::INITIAL_MU)) {
                $cs->mu_peak      = $new['mu'];
                $cs->mu_peak_date = $now->toDateString();
            }
        }
    }

    // ---------------------------------------------------------------
    //  Статистика пар (внутри команды)
    // ---------------------------------------------------------------

    private function updatePairStats(array $userIds, bool $won, string $direction = 'beach', ?string $gameScheme = null): void
    {
        $n = count($userIds);
        if ($n < 2) return;

        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $p1 = min($userIds[$i], $userIds[$j]);
                $p2 = max($userIds[$i], $userIds[$j]);

                DB::statement("
                    INSERT INTO player_pair_stats
                        (player1_id, player2_id, direction, game_scheme, matches_together, wins_together, created_at, updated_at)
                    VALUES (?, ?, ?, ?, 1, ?, NOW(), NOW())
                    ON CONFLICT (player1_id, player2_id) DO UPDATE
                    SET matches_together = player_pair_stats.matches_together + 1,
                        wins_together    = player_pair_stats.wins_together + EXCLUDED.wins_together,
                        direction        = EXCLUDED.direction,
                        game_scheme      = COALESCE(EXCLUDED.game_scheme, player_pair_stats.game_scheme),
                        updated_at       = NOW()
                ", [$p1, $p2, $direction, $gameScheme, $won ? 1 : 0]);
            }
        }
    }

    // ---------------------------------------------------------------
    //  Статистика соперников (между командами)
    // ---------------------------------------------------------------

    private function updateOpponentStats(array $winnerIds, array $loserIds): void
    {
        foreach ($winnerIds as $wId) {
            foreach ($loserIds as $lId) {
                // победитель победил над проигравшим
                DB::statement("
                    INSERT INTO player_opponent_stats
                        (user_id, opponent_id, matches_against, wins_against, created_at, updated_at)
                    VALUES (?, ?, 1, 1, NOW(), NOW())
                    ON CONFLICT (user_id, opponent_id) DO UPDATE
                    SET matches_against = player_opponent_stats.matches_against + 1,
                        wins_against    = player_opponent_stats.wins_against + 1,
                        updated_at      = NOW()
                ", [$wId, $lId]);

                // проигравший проиграл победителю
                DB::statement("
                    INSERT INTO player_opponent_stats
                        (user_id, opponent_id, matches_against, wins_against, created_at, updated_at)
                    VALUES (?, ?, 1, 0, NOW(), NOW())
                    ON CONFLICT (user_id, opponent_id) DO UPDATE
                    SET matches_against = player_opponent_stats.matches_against + 1,
                        updated_at      = NOW()
                ", [$lId, $wId]);
            }
        }
    }

    // ---------------------------------------------------------------
    //  Производные метрики
    // ---------------------------------------------------------------

    private function recalcDerivedStats(int $userId, string $direction, ?PlayerCareerStats $cs = null): void
    {
        if (!$cs) {
            $cs = PlayerCareerStats::where('user_id', $userId)
                ->where('direction', $direction)
                ->first();
            if (!$cs) return;
        }

        // Форма — последние 10 матчей (новые первыми)
        $history = DB::table('player_rating_history')
            ->where('user_id', $userId)
            ->orderByDesc('recorded_at')
            ->limit(10)
            ->get(['mu_before', 'mu_after']);

        $results = $history->map(fn($h) => $h->mu_after > $h->mu_before ? 'В' : 'П')->values();
        $cs->last_5_form  = $results->take(5)->implode('');
        $cs->last_10_form = $results->take(10)->implode('');

        // Уникальные соперники
        $cs->unique_opponents = (int) DB::table('player_opponent_stats')
            ->where('user_id', $userId)
            ->count();

        // Уникальные партнёры + основной партнёр
        $pairs = DB::table('player_pair_stats')
            ->where('player1_id', $userId)
            ->orWhere('player2_id', $userId)
            ->orderByDesc('matches_together')
            ->get(['player1_id', 'player2_id', 'matches_together']);

        $cs->unique_partners = $pairs->count();

        $topPair = $pairs->first();
        if ($topPair) {
            $cs->main_partner_id    = ((int) $topPair->player1_id === $userId)
                ? (int) $topPair->player2_id
                : (int) $topPair->player1_id;
            $cs->main_partner_games = (int) $topPair->matches_together;
            $cs->pair_stability     = ($cs->total_matches ?? 0) > 0
                ? round($topPair->matches_together / $cs->total_matches * 100, 2)
                : 0.0;
        } else {
            $cs->main_partner_id    = null;
            $cs->main_partner_games = 0;
            $cs->pair_stability     = 0.0;
        }

        // Коэффициент очков
        $cs->points_ratio = ($cs->total_points_conceded ?? 0) > 0
            ? round(($cs->total_points_scored ?? 0) / $cs->total_points_conceded, 3)
            : 1.0;

        $cs->save();
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
        return array_map(fn($uid) => [
            'mu'    => (float) ($modelMap->get($uid)?->$muField    ?? self::INITIAL_MU),
            'sigma' => (float) ($modelMap->get($uid)?->$sigmaField ?? self::INITIAL_SIGMA),
        ], $userIds);
    }

    private function saveCareerRatings(array $userIds, array $newRatings, $careerMap): void
    {
        foreach ($userIds as $idx => $uid) {
            $cs = $careerMap->get($uid);
            if (!$cs) continue;
            $cs->mu    = $newRatings[$idx]['mu'];
            $cs->sigma = $newRatings[$idx]['sigma'];
            // mu_peak / mu_peak_date уже выставлены в recordHistoryAndPeak
            $cs->save();
        }
    }

    private function saveSeasonRatings(array $userIds, array $newRatings, $seasonMap, int $seasonId, int $leagueId): void
    {
        foreach ($userIds as $idx => $uid) {
            $ss = $seasonMap->get($uid);
            if (!$ss) continue;
            $ss->mu_season    = $newRatings[$idx]['mu'];
            $ss->sigma_season = $newRatings[$idx]['sigma'];
            $ss->save();
        }
    }
}
