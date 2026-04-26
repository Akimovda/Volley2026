<?php

namespace App\Services;

use App\Models\TournamentSeason;
use App\Models\TournamentLeague;
use App\Models\TournamentLeagueTeam;
use App\Models\TournamentSeasonEvent;
use App\Models\EventTeam;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Services\UserNotificationService;
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

        // Проверка уровня членов команды
        if ($team) {
            $this->validateTeamLevel($team, $league);
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
    public function fillFromReserve(TournamentLeague $league, int $slots = 1, bool $requireConfirmation = true): Collection
    {
        $offered = collect();

        if (!$league->hasCapacity()) {
            return $offered;
        }

        // Не считаем pending_confirmation как свободные слоты
        $pendingCount = TournamentLeagueTeam::where('league_id', $league->id)
            ->where('status', TournamentLeagueTeam::STATUS_PENDING_CONFIRMATION)
            ->count();

        $activeCount = TournamentLeagueTeam::where('league_id', $league->id)
            ->where('status', TournamentLeagueTeam::STATUS_ACTIVE)
            ->count();

        $available = ($league->max_teams ?? PHP_INT_MAX) - $activeCount - $pendingCount;
        if ($available <= 0) {
            return $offered;
        }

        $reserves = $league->reserveTeams()
            ->orderBy('reserve_position')
            ->limit(min($slots, $available))
            ->get();

        foreach ($reserves as $reserve) {
            if ($requireConfirmation) {
                $reserve->offerSpot();

                // Отправляем уведомление капитану
                try {
                    $this->notifyReserveOffer($reserve);
                } catch (\Throwable $e) {
                    report($e);
                }

                // Уведомляем организатора
                try {
                    $this->notifyOrganizerReserveOffer($league, $reserve);
                } catch (\Throwable $e) {
                    report($e);
                }

                // Создаём job для таймаута
                \App\Jobs\ExpireReserveConfirmationJob::dispatch($reserve->id)
                    ->delay(now()->addHours(2));
            } else {
                $reserve->activateFromReserve();
            }

            $offered->push($reserve->fresh());
        }

        $this->reindexReserve($league);

        return $offered;
    }

    /**
     * Подтвердить место из резерва (капитан подтверждает + оплата).
     */
    public function confirmReserveSpot(TournamentLeagueTeam $leagueTeam): void
    {
        if (!$leagueTeam->isPendingConfirmation()) {
            throw new InvalidArgumentException('Команда не ожидает подтверждения.');
        }

        if ($leagueTeam->confirmation_expires_at && $leagueTeam->confirmation_expires_at->isPast()) {
            throw new InvalidArgumentException('Время подтверждения истекло.');
        }

        $leagueTeam->confirmSpot();
    }

    /**
     * Таймаут подтверждения — возврат в конец резерва.
     */
    public function expireReserveOffer(TournamentLeagueTeam $leagueTeam): void
    {
        if (!$leagueTeam->isPendingConfirmation()) {
            return; // Уже подтвердили или отменили
        }

        $league = $leagueTeam->league;
        $newPosition = $league->nextReservePosition();
        $leagueTeam->expireConfirmation($newPosition);

        // Предлагаем место следующей команде
        $this->fillFromReserve($league, 1);
    }

    /**
     * Уведомление капитану о предложении места.
     */
    private function notifyReserveOffer(TournamentLeagueTeam $leagueTeam): void
    {
        $team = $leagueTeam->team;
        if (!$team || !$team->captain_user_id) return;

        $league = $leagueTeam->league;
        $season = $league->season ?? null;
        $confirmUrl = route('league.reserve.confirm', ['token' => $leagueTeam->confirmation_token]);
        $expiresAt = $leagueTeam->confirmation_expires_at?->format('d.m.Y H:i');

        $title = 'Освободилось место в лиге!';
        $body = "В лиге «{$league->name}» освободилось место для вашей команды «{$team->name}». "
            . "Подтвердите участие и оплатите до {$expiresAt}. "
            . "Если не подтвердите — место перейдёт следующей команде.";

        app(UserNotificationService::class)->create(
            userId: (int) $team->captain_user_id,
            type: 'reserve_spot_offered',
            title: $title,
            body: $body,
            payload: [
                'league_id' => $league->id,
                'league_team_id' => $leagueTeam->id,
                'team_id' => $team->id,
                'confirm_url' => $confirmUrl,
                'expires_at' => $expiresAt,
                'button_text' => 'Подтвердить участие',
                'button_url' => $confirmUrl,
            ],
            channels: ['in_app', 'telegram', 'vk', 'max']
        );
    }

    /**
     * Уведомление организатора о том, что команда из резерва приглашена на место.
     */
    private function notifyOrganizerReserveOffer(TournamentLeague $league, TournamentLeagueTeam $leagueTeam): void
    {
        $season = $league->season;
        $event = \App\Models\TournamentSeasonEvent::where('season_id', $season->id)
            ->whereHas('event', fn($q) => $q->whereNotNull('organizer_id'))
            ->first()?->event;

        if (!$event?->organizer_id) return;

        $teamName = $leagueTeam->team?->name ?? 'Команда #' . $leagueTeam->id;

        app(UserNotificationService::class)->create(
            userId: (int) $event->organizer_id,
            type: 'reserve_spot_offered_organizer',
            title: 'Команда из резерва приглашена',
            body: "Команда «{$teamName}» из резерва лиги «{$league->name}» получила приглашение. Ожидаем подтверждения от капитана (2 часа).",
            payload: [
                'league_id' => $league->id,
                'league_team_id' => $leagueTeam->id,
            ],
            channels: ['in_app', 'telegram']
        );
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

    /**
     * Проверка уровня всех игроков команды для лиги.
     */
    private function validateTeamLevel(EventTeam $team, TournamentLeague $league): void
    {
        $season = $league->season;
        if (!$season) return;

        // Берём event из сезона для определения уровня
        $event = $season->events()->latest()->first();
        if (!$event) return;

        $direction = $event->direction ?? 'classic';

        if ($direction === 'beach') {
            $lvMin = $event->beach_level_min;
            $lvMax = $event->beach_level_max;
            $levelField = 'beach_level';
        } else {
            $lvMin = $event->classic_level_min;
            $lvMax = $event->classic_level_max;
            $levelField = 'classic_level';
        }

        if (is_null($lvMin) && is_null($lvMax)) return;

        $members = $team->members()
            ->whereIn('confirmation_status', ['confirmed', 'self'])
            ->with('user')
            ->get();

        $violations = [];
        foreach ($members as $member) {
            $user = $member->user;
            if (!$user) continue;

            $userLevel = $user->{$levelField};
            if (is_null($userLevel)) continue;

            if ((!is_null($lvMin) && $userLevel < $lvMin) || (!is_null($lvMax) && $userLevel > $lvMax)) {
                $name = trim(($user->last_name ?? '') . ' ' . ($user->first_name ?? ''));
                $violations[] = $name ?: "Игрок #{$user->id}";
            }
        }

        if (!empty($violations)) {
            throw new InvalidArgumentException(
                'Игрок(и) ' . implode(', ', $violations) . ' не соответствуют требованиям по уровню лиги.'
            );
        }
    }

}
