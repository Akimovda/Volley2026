<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\EventRegistration;
use App\Models\EventTeam;
use App\Models\EventTeamMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TournamentTeamDistributionService
{
    public function __construct(
        private TournamentTeamNamingService $namingService,
    ) {
    }

    /**
     * Случайно распределяет индивидуально записавшихся игроков по командам.
     *
     * Алгоритм:
     *  1. Берём все активные регистрации, разбиваем по позициям.
     *  2. Для каждой позиции перемешиваем список игроков.
     *  3. Распределяем по командам round-robin: игрок [i] → команда [i % N].
     *  4. Создаём N EventTeam + EventTeamMember (первый игрок в команде = капитан).
     *
     * Возвращает ['ok' => bool, 'message' => string, 'teams_count' => int]
     */
    public function distributeRandom(Event $event, EventOccurrence $occurrence): array
    {
        if ($event->registration_mode !== 'tournament_individual') {
            return ['ok' => false, 'message' => __('events.tournament_distribute_not_individual')];
        }

        $teamsCount = (int)($event->tournament_teams_count ?? 0);
        if ($teamsCount < 2) {
            return ['ok' => false, 'message' => 'Не задано количество команд.'];
        }

        // Проверяем: уже есть команды?
        $existingTeams = EventTeam::where('event_id', $event->id)
            ->where(fn($q) => $q->where('occurrence_id', $occurrence->id)->orWhereNull('occurrence_id'))
            ->exists();

        if ($existingTeams) {
            return ['ok' => false, 'message' => __('events.tournament_distribute_error_exists')];
        }

        // Все активные регистрации
        $registrations = EventRegistration::where('occurrence_id', $occurrence->id)
            ->whereNull('cancelled_at')
            ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
            ->get();

        if ($registrations->isEmpty()) {
            return ['ok' => false, 'message' => __('events.tournament_distribute_error_no_players')];
        }

        // Группируем по позиции
        $byPosition = $registrations->groupBy('position')->map(fn($group) => $group->pluck('user_id')->shuffle()->values());

        // Собираем все назначения: [['user_id' => X, 'position' => Y, 'team_idx' => Z]]
        $assignments = [];
        foreach ($byPosition as $position => $userIds) {
            foreach ($userIds as $i => $userId) {
                $assignments[] = [
                    'user_id'   => $userId,
                    'position'  => $position ?: 'player',
                    'team_idx'  => $i % $teamsCount,
                ];
            }
        }

        if (empty($assignments)) {
            return ['ok' => false, 'message' => __('events.tournament_distribute_error_no_players')];
        }

        // Группируем назначения по команде
        $teamAssignments = [];
        for ($i = 0; $i < $teamsCount; $i++) {
            $teamAssignments[$i] = array_filter($assignments, fn($a) => $a['team_idx'] === $i);
        }

        $direction = $event->direction ?? 'classic';
        $teamKind  = ($direction === 'beach') ? 'beach_pair' : 'classic_team';

        $usersById = User::whereIn('id', $registrations->pluck('user_id'))->get()->keyBy('id');

        DB::transaction(function () use ($event, $occurrence, $teamsCount, $teamAssignments, $teamKind, $usersById) {
            for ($i = 0; $i < $teamsCount; $i++) {
                $members = array_values($teamAssignments[$i]);
                if (empty($members)) {
                    continue;
                }

                $memberUsers = collect($members)
                    ->map(fn ($member) => $usersById->get($member['user_id']))
                    ->filter()
                    ->values();

                $team = EventTeam::create([
                    'event_id'         => $event->id,
                    'occurrence_id'    => $occurrence->id,
                    'captain_user_id'  => $members[0]['user_id'],
                    'name'             => $this->namingService->generate($event, $memberUsers, $occurrence->id),
                    'team_kind'        => $teamKind,
                    'status'           => 'approved',
                    'is_complete'      => true,
                    'confirmed_at'     => now(),
                    'invite_code'      => substr(md5(uniqid((string)$i, true)), 0, 8),
                ]);

                foreach ($members as $j => $member) {
                    $isCaptain = ($j === 0);
                    EventTeamMember::create([
                        'event_team_id'       => $team->id,
                        'user_id'             => $member['user_id'],
                        'role_code'           => $isCaptain ? 'captain' : $member['position'],
                        'position_code'       => $member['position'],
                        'confirmation_status' => 'confirmed',
                        'joined_at'           => now(),
                        'confirmed_at'        => now(),
                    ]);
                }
            }
        });

        return [
            'ok'          => true,
            'message'     => __('events.tournament_distribute_success'),
            'teams_count' => $teamsCount,
        ];
    }
}
