<?php

namespace App\Services;

use App\Models\Event;
use App\Models\MatchPlayerStats;
use App\Models\TournamentMatch;
use App\Models\PlayerTournamentStats;
use App\Models\PlayerCareerStats;
use Illuminate\Support\Facades\DB;

class PlayerMatchStatsService
{
    /**
     * Статистические поля (без points_scored — он вычисляемый).
     */
    public const STAT_FIELDS = [
        'serves_total', 'aces', 'serve_errors',
        'attacks_total', 'kills', 'attack_errors',
        'blocks', 'block_errors',
        'digs', 'reception_errors',
        'assists',
    ];

    /**
     * Маппинг: match_player_stats field → player_tournament_stats field.
     */
    private const FIELD_MAP = [
        'serves_total'    => 'total_serves',
        'aces'            => 'total_aces',
        'serve_errors'    => 'total_serve_errors',
        'attacks_total'   => 'total_attacks',
        'kills'           => 'total_kills',
        'attack_errors'   => 'total_attack_errors',
        'blocks'          => 'total_blocks',
        'block_errors'    => 'total_block_errors',
        'digs'            => 'total_digs',
        'reception_errors'=> 'total_reception_errors',
        'assists'         => 'total_assists',
    ];

    /**
     * Сохранить статистику за конкретный сет (или весь матч если set_number=0).
     *
     * @param TournamentMatch $match
     * @param int $setNumber  0 = весь матч, 1-5 = сет
     * @param int $userId
     * @param int $teamId
     * @param array $data     ключи из STAT_FIELDS
     * @return MatchPlayerStats
     */
    public function saveSetStats(TournamentMatch $match, int $setNumber, int $userId, int $teamId, array $data): MatchPlayerStats
    {
        $fillData = ['team_id' => $teamId];
        foreach (self::STAT_FIELDS as $field) {
            $fillData[$field] = max(0, (int) ($data[$field] ?? 0));
        }

        $stat = MatchPlayerStats::updateOrCreate(
            [
                'match_id'   => $match->id,
                'set_number' => $setNumber,
                'user_id'    => $userId,
            ],
            $fillData
        );

        $stat->calcPoints()->save();

        return $stat;
    }

    /**
     * Массовое сохранение статистики за матч от организатора.
     * Формат: $playersData = [userId => [setNumber => [field => value, ...], ...], ...]
     */
    public function saveBulk(TournamentMatch $match, int $teamId, array $playersData): void
    {
        foreach ($playersData as $userId => $sets) {
            foreach ($sets as $setNumber => $data) {
                $this->saveSetStats($match, (int) $setNumber, (int) $userId, $teamId, $data);
            }
        }
    }

    /**
     * Получить статистику матча для UI (обе команды, по сетам + итого).
     *
     * @return array{home: array, away: array, sets_count: int}
     */
    public function getMatchStatsTable(TournamentMatch $match): array
    {
        $allStats = MatchPlayerStats::where('match_id', $match->id)
            ->with('user')
            ->get();

        if ($allStats->isEmpty()) {
            return ['home' => [], 'away' => [], 'sets_count' => 0, 'has_stats' => false];
        }

        $setsCount = $allStats->where('set_number', '>', 0)->max('set_number') ?? 0;

        $home = $allStats->where('team_id', $match->team_home_id)->groupBy('user_id');
        $away = $allStats->where('team_id', $match->team_away_id)->groupBy('user_id');

        return [
            'home'      => $this->formatTeamStats($home, $setsCount),
            'away'      => $this->formatTeamStats($away, $setsCount),
            'sets_count' => $setsCount,
            'has_stats' => true,
        ];
    }

    /**
     * Форматировать статистику команды для отображения.
     */
    private function formatTeamStats($groupedByUser, int $setsCount): array
    {
        $result = [];
        foreach ($groupedByUser as $userId => $stats) {
            $user = $stats->first()->user;
            $playerData = [
                'user_id'   => $userId,
                'user_name' => $user ? trim($user->last_name . ' ' . $user->first_name) : "Игрок #{$userId}",
                'sets'      => [],
                'totals'    => null,
            ];

            foreach ($stats as $stat) {
                if ($stat->set_number === 0) {
                    $playerData['totals'] = $stat;
                } else {
                    $playerData['sets'][$stat->set_number] = $stat;
                }
            }

            // Если нет totals но есть сеты — рассчитываем суммарную строку
            if (!$playerData['totals'] && !empty($playerData['sets'])) {
                $playerData['totals'] = $this->sumSets($playerData['sets']);
            }

            $result[] = $playerData;
        }
        return $result;
    }

    /**
     * Суммировать статистику по сетам для итоговой строки.
     */
    private function sumSets(array $sets): array
    {
        $totals = array_fill_keys(self::STAT_FIELDS, 0);
        foreach ($sets as $stat) {
            foreach (self::STAT_FIELDS as $field) {
                $totals[$field] += $stat->$field;
            }
        }
        $totals['points_scored'] = $totals['aces'] + $totals['kills'] + $totals['blocks'];
        return $totals;
    }

    /**
     * Агрегировать детальную статистику match_player_stats → player_tournament_stats.
     */
    public function aggregateToTournament(Event $event): void
    {
        $eventId = $event->id;

        // Все match_player_stats для матчей этого турнира
        $matchIds = TournamentMatch::whereHas('stage', fn($q) => $q->where('event_id', $eventId))
            ->where('status', TournamentMatch::STATUS_COMPLETED)
            ->pluck('id');

        if ($matchIds->isEmpty()) return;

        // Группируем по user_id + team_id, суммируем
        $aggregated = MatchPlayerStats::whereIn('match_id', $matchIds)
            ->select(
                'user_id', 'team_id',
                DB::raw('SUM(serves_total) as sum_serves'),
                DB::raw('SUM(aces) as sum_aces'),
                DB::raw('SUM(serve_errors) as sum_serve_errors'),
                DB::raw('SUM(attacks_total) as sum_attacks'),
                DB::raw('SUM(kills) as sum_kills'),
                DB::raw('SUM(attack_errors) as sum_attack_errors'),
                DB::raw('SUM(blocks) as sum_blocks'),
                DB::raw('SUM(block_errors) as sum_block_errors'),
                DB::raw('SUM(digs) as sum_digs'),
                DB::raw('SUM(reception_errors) as sum_reception_errors'),
                DB::raw('SUM(assists) as sum_assists'),
            )
            ->groupBy('user_id', 'team_id')
            ->get();

        foreach ($aggregated as $row) {
            $stat = PlayerTournamentStats::where('event_id', $eventId)
                ->where('user_id', $row->user_id)
                ->where('team_id', $row->team_id)
                ->first();

            if (!$stat) continue;

            $stat->total_serves          = $row->sum_serves;
            $stat->total_aces            = $row->sum_aces;
            $stat->total_serve_errors    = $row->sum_serve_errors;
            $stat->total_attacks         = $row->sum_attacks;
            $stat->total_kills           = $row->sum_kills;
            $stat->total_attack_errors   = $row->sum_attack_errors;
            $stat->total_blocks          = $row->sum_blocks;
            $stat->total_block_errors    = $row->sum_block_errors;
            $stat->total_digs            = $row->sum_digs;
            $stat->total_reception_errors = $row->sum_reception_errors;
            $stat->total_assists         = $row->sum_assists;

            $stat->recalcRates()->save();
        }
    }

    /**
     * Агрегировать детальную статистику player_tournament_stats → player_career_stats.
     */
    public function aggregateToCareer(int $userId): void
    {
        foreach (['classic', 'beach'] as $direction) {
            $tournamentStats = PlayerTournamentStats::where('user_id', $userId)
                ->whereHas('event', fn($q) => $q->where('direction', $direction))
                ->get();

            $career = PlayerCareerStats::where('user_id', $userId)
                ->where('direction', $direction)
                ->first();

            if (!$career) continue;

            $career->total_serves          = $tournamentStats->sum('total_serves');
            $career->total_aces            = $tournamentStats->sum('total_aces');
            $career->total_serve_errors    = $tournamentStats->sum('total_serve_errors');
            $career->total_attacks         = $tournamentStats->sum('total_attacks');
            $career->total_kills           = $tournamentStats->sum('total_kills');
            $career->total_attack_errors   = $tournamentStats->sum('total_attack_errors');
            $career->total_blocks          = $tournamentStats->sum('total_blocks');
            $career->total_block_errors    = $tournamentStats->sum('total_block_errors');
            $career->total_digs            = $tournamentStats->sum('total_digs');
            $career->total_reception_errors = $tournamentStats->sum('total_reception_errors');
            $career->total_assists         = $tournamentStats->sum('total_assists');
            $career->mvp_count             = $tournamentStats->sum('mvp_count');

            $career->recalcRates()->save();
        }
    }

    /**
     * Удалить всю детальную статистику матча.
     */
    public function deleteMatchStats(TournamentMatch $match): int
    {
        return MatchPlayerStats::where('match_id', $match->id)->delete();
    }

    /**
     * Проверить, есть ли статистика для матча.
     */
    public function hasStats(TournamentMatch $match): bool
    {
        return MatchPlayerStats::where('match_id', $match->id)->exists();
    }

    /**
     * Список игроков обеих команд для UI формы ввода.
     */
    public function getMatchPlayers(TournamentMatch $match): array
    {
        $home = DB::table('event_team_members')
            ->join('users', 'users.id', '=', 'event_team_members.user_id')
            ->where('event_team_id', $match->team_home_id)
            ->where('confirmation_status', 'confirmed')
            ->select('users.id', 'users.first_name', 'users.last_name')
            ->get();

        $away = DB::table('event_team_members')
            ->join('users', 'users.id', '=', 'event_team_members.user_id')
            ->where('event_team_id', $match->team_away_id)
            ->where('confirmation_status', 'confirmed')
            ->select('users.id', 'users.first_name', 'users.last_name')
            ->get();

        return [
            'home' => $home->map(fn($u) => [
                'id' => $u->id,
                'name' => trim(($u->last_name ?? '') . ' ' . ($u->first_name ?? '')),
            ])->toArray(),
            'away' => $away->map(fn($u) => [
                'id' => $u->id,
                'name' => trim(($u->last_name ?? '') . ' ' . ($u->first_name ?? '')),
            ])->toArray(),
        ];
    }
}
