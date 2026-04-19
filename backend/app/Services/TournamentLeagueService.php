<?php

namespace App\Services;

use App\Models\TournamentSeason;
use App\Models\TournamentLeague;
use App\Models\TournamentLeagueTeam;
use App\Models\EventTeam;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TournamentLeagueService
{
    /**
     * Создать лигу в сезоне.
     */
    public function createLeague(TournamentSeason $season, array $data): TournamentLeague
    {
        $maxOrder = $season->leagues()->max('sort_order') ?? 0;

        return TournamentLeague::create([
            'season_id'  => $season->id,
            'name'       => $data['name'],
            'level'      => $data['level'] ?? ($maxOrder + 1),
            'sort_order' => $data['sort_order'] ?? ($maxOrder + 1),
            'max_teams'  => $data['max_teams'] ?? null,
            'config'     => $data['config'] ?? [],
        ]);
    }

    /**
     * Обновить лигу.
     */
    public function updateLeague(TournamentLeague $league, array $data): TournamentLeague
    {
        $fillable = ['name', 'level', 'sort_order', 'max_teams', 'config'];
        $league->update(array_intersect_key($data, array_flip($fillable)));
        return $league->fresh();
    }

    /**
     * Добавить команду в лигу.
     */
    public function addTeam(
        TournamentLeague $league,
        ?EventTeam $team = null,
        ?User $user = null,
    ): TournamentLeagueTeam {
        if (!$team && !$user) {
            throw new InvalidArgumentException('Укажите team или user.');
        }

        // Проверка на дубликат
        $query = TournamentLeagueTeam::where('league_id', $league->id)
            ->whereIn('status', ['active', 'reserve']);

        if ($team) {
            $exists = (clone $query)->where('team_id', $team->id)->exists();
        } else {
            $exists = (clone $query)->where('user_id', $user->id)->exists();
        }

        if ($exists) {
            throw new InvalidArgumentException('Команда/игрок уже в этой лиге.');
        }

        // Есть ли место?
        if ($league->hasCapacity()) {
            return TournamentLeagueTeam::create([
                'league_id' => $league->id,
                'team_id'   => $team?->id,
                'user_id'   => $user?->id,
                'status'    => TournamentLeagueTeam::STATUS_ACTIVE,
                'joined_at' => now(),
            ]);
        }

        // В резерв
        return TournamentLeagueTeam::create([
            'league_id'        => $league->id,
            'team_id'          => $team?->id,
            'user_id'          => $user?->id,
            'status'           => TournamentLeagueTeam::STATUS_RESERVE,
            'joined_at'        => now(),
            'reserve_position' => $league->nextReservePosition(),
        ]);
    }

    /**
     * Убрать команду из лиги.
     */
    public function removeTeam(TournamentLeagueTeam $leagueTeam): void
    {
        $league = $leagueTeam->league;
        $leagueTeam->delete();

        // Если освободилось место — подтягиваем из резерва
        $this->fillFromReserve($league);
    }

    /**
     * Подтянуть из резерва в основной состав.
     */
    public function fillFromReserve(TournamentLeague $league, int $slots = 1): Collection
    {
        $promoted = collect();

        if (!$league->hasCapacity()) {
            return $promoted;
        }

        $reserves = $league->reserveTeams()
            ->orderBy('reserve_position')
            ->limit($slots)
            ->get();

        foreach ($reserves as $reserve) {
            if (!$league->hasCapacity()) {
                break;
            }

            $reserve->activateFromReserve();
            $promoted->push($reserve->fresh());
        }

        // Пересчитываем позиции оставшихся в резерве
        $this->reindexReserve($league);

        return $promoted;
    }

    /**
     * Пересчитать reserve_position (1, 2, 3...).
     */
    public function reindexReserve(TournamentLeague $league): void
    {
        $reserves = TournamentLeagueTeam::where('league_id', $league->id)
            ->where('status', TournamentLeagueTeam::STATUS_RESERVE)
            ->orderBy('reserve_position')
            ->get();

        foreach ($reserves->values() as $i => $r) {
            $r->update(['reserve_position' => $i + 1]);
        }
    }

    /**
     * Переместить команду в другую лигу (promote/relegate).
     */
    public function transferToLeague(
        TournamentLeagueTeam $leagueTeam,
        TournamentLeague $targetLeague,
        string $reason = 'promoted',
    ): TournamentLeagueTeam {
        $sourceLeague = $leagueTeam->league;

        // Помечаем в исходной лиге
        $leagueTeam->update([
            'status' => $reason, // promoted | relegated
            'left_at' => now(),
        ]);

        // Создаём в целевой
        $newEntry = TournamentLeagueTeam::create([
            'league_id' => $targetLeague->id,
            'team_id'   => $leagueTeam->team_id,
            'user_id'   => $leagueTeam->user_id,
            'status'    => $targetLeague->hasCapacity()
                ? TournamentLeagueTeam::STATUS_ACTIVE
                : TournamentLeagueTeam::STATUS_RESERVE,
            'joined_at' => now(),
            'reserve_position' => $targetLeague->hasCapacity()
                ? null
                : $targetLeague->nextReservePosition(),
        ]);

        // Освободилось место в исходной лиге — подтягиваем из резерва
        $this->fillFromReserve($sourceLeague);

        return $newEntry;
    }

    /**
     * Отправить команду в резерв (eliminate).
     */
    public function eliminateToReserve(TournamentLeagueTeam $leagueTeam): void
    {
        $league = $leagueTeam->league;
        $leagueTeam->eliminate($league->nextReservePosition());

        $this->fillFromReserve($league);
    }

    /**
     * Получить standings лиги — активные команды с их сезонной статистикой.
     */
    public function getLeagueStandings(TournamentLeague $league): Collection
    {
        return TournamentLeagueTeam::where('league_id', $league->id)
            ->where('status', TournamentLeagueTeam::STATUS_ACTIVE)
            ->with(['team.captain', 'user'])
            ->get()
            ->sortByDesc(function (TournamentLeagueTeam $lt) use ($league) {
                $userId = $lt->user_id ?? $lt->team?->captain_id;
                if (!$userId) return 0;

                $stats = \App\Models\TournamentSeasonStats::where('season_id', $league->season_id)
                    ->where('league_id', $league->id)
                    ->where('user_id', $userId)
                    ->first();

                return $stats?->match_win_rate ?? 0;
            })
            ->values();
    }

    /**
     * Удалить лигу (только если нет команд).
     */
    public function deleteLeague(TournamentLeague $league): void
    {
        if ($league->leagueTeams()->count() > 0) {
            throw new InvalidArgumentException('Нельзя удалить лигу с командами.');
        }

        $league->delete();
    }
}
