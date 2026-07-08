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
     * Случайно распределяет ещё НЕраспределённых индивидуально записавшихся игроков
     * по оставшимся командным слотам — уже созданные вручную/ранее команды не трогает
     * и не пересоздаёт, их игроки исключаются из выборки.
     *
     * Алгоритм:
     *  1. Берём активные регистрации БЕЗ тех, кто уже состоит в какой-либо команде
     *     этого события/тура, разбиваем по позициям.
     *  2. Команды из 2 человек (пляжные пары) — сначала смешанные М+Ж пары
     *     (пока хватает обоих полов), остаток одного пола — пары того же пола.
     *     Команды другого размера — просто перемешиваем и round-robin: игрок [i] → команда [i % N].
     *  3. Создаём недостающие EventTeam + EventTeamMember (первый игрок в паре/группе = капитан),
     *     N = tournament_teams_count минус уже существующие команды.
     *
     * Возвращает ['ok' => bool, 'message' => string, 'teams_count' => int]
     */
    public function distributeRandom(Event $event, EventOccurrence $occurrence): array
    {
        if ($event->registration_mode !== 'tournament_individual') {
            return ['ok' => false, 'message' => __('events.tournament_distribute_not_individual')];
        }

        $totalTeamsCount = (int)($event->tournament_teams_count ?? 0);
        if ($totalTeamsCount < 2) {
            return ['ok' => false, 'message' => 'Не задано количество команд.'];
        }

        $existingTeamsCount = EventTeam::where('event_id', $event->id)
            ->where(fn($q) => $q->where('occurrence_id', $occurrence->id)->orWhereNull('occurrence_id'))
            ->count();

        $teamsCount = $totalTeamsCount - $existingTeamsCount;
        if ($teamsCount < 1) {
            return ['ok' => false, 'message' => __('events.tournament_distribute_error_exists')];
        }

        // Игроки, уже состоящие в какой-либо команде этого события/тура — не трогаем.
        $assignedUserIds = EventTeamMember::whereHas(
            'team',
            fn($q) => $q->where('event_id', $event->id)->where('occurrence_id', $occurrence->id)
        )->pluck('user_id');

        // Активные регистрации, которые ещё не распределены по командам
        $registrations = EventRegistration::where('occurrence_id', $occurrence->id)
            ->whereNull('cancelled_at')
            ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
            ->whereNotIn('user_id', $assignedUserIds)
            ->get();

        if ($registrations->isEmpty()) {
            return ['ok' => false, 'message' => __('events.tournament_distribute_error_no_players')];
        }

        $usersById = User::whereIn('id', $registrations->pluck('user_id'))->get()->keyBy('id');

        // Пары (team_size_min=2, как в 2x2) — приоритет смешанным М+Ж парам.
        $teamSizeMin = (int) ($event->tournamentSetting?->team_size_min ?? 0);

        $byPosition = $registrations->groupBy('position')->map(fn($group) => $group->pluck('user_id')->values());

        // Собираем все назначения: [['user_id' => X, 'position' => Y, 'team_idx' => Z]]
        $assignments = [];
        foreach ($byPosition as $position => $userIds) {
            if ($teamSizeMin === 2) {
                $groupIdx = 0;
                foreach ($this->pairByGenderThenRandom($userIds, $usersById) as $pair) {
                    $teamIdx = $groupIdx % $teamsCount;
                    foreach ($pair as $userId) {
                        $assignments[] = [
                            'user_id'  => $userId,
                            'position' => $position ?: 'player',
                            'team_idx' => $teamIdx,
                        ];
                    }
                    $groupIdx++;
                }
            } else {
                foreach ($userIds->shuffle()->values() as $i => $userId) {
                    $assignments[] = [
                        'user_id'   => $userId,
                        'position'  => $position ?: 'player',
                        'team_idx'  => $i % $teamsCount,
                    ];
                }
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

        $createdCount = 0;

        DB::transaction(function () use ($event, $occurrence, $teamsCount, $teamAssignments, $teamKind, $usersById, $teamSizeMin, &$createdCount) {
            for ($i = 0; $i < $teamsCount; $i++) {
                $members = array_values($teamAssignments[$i]);
                if (empty($members)) {
                    continue;
                }

                $memberUsers = collect($members)
                    ->map(fn ($member) => $usersById->get($member['user_id']))
                    ->filter()
                    ->values();

                $isComplete = $teamSizeMin > 0
                    ? count($members) >= $teamSizeMin
                    : true;

                $team = EventTeam::create([
                    'event_id'         => $event->id,
                    'occurrence_id'    => $occurrence->id,
                    'captain_user_id'  => $members[0]['user_id'],
                    'name'             => $this->namingService->generate($event, $memberUsers, $occurrence->id),
                    'team_kind'        => $teamKind,
                    'status'           => 'approved',
                    'is_complete'      => $isComplete,
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

                $createdCount++;
            }
        });

        return [
            'ok'          => true,
            'message'     => __('events.tournament_distribute_success'),
            'teams_count' => $createdCount,
        ];
    }

    /**
     * Разбивает игроков на пары для команд из 2 человек: сначала максимум смешанных
     * М+Ж пар (пока хватает обоих полов), остаток (перевес одного пола + неизвестный
     * пол) — парами вперемешку между собой. Нечётный "хвост" — неполная пара из 1 игрока
     * (команда останется "ищет партнёра", как и при обычном ручном создании).
     *
     * @param \Illuminate\Support\Collection<int, int> $userIds
     * @param \Illuminate\Support\Collection<int, \App\Models\User> $usersById
     * @return array<int, array<int>> список пар (или одиночных остатков) user_id
     */
    private function pairByGenderThenRandom($userIds, $usersById): array
    {
        $gender = fn (int $id) => $usersById->get($id)?->gender;

        $males   = $userIds->filter(fn ($id) => $gender($id) === 'm')->shuffle()->values();
        $females = $userIds->filter(fn ($id) => $gender($id) === 'f')->shuffle()->values();
        $rest    = $userIds->reject(fn ($id) => in_array($gender($id), ['m', 'f'], true))->shuffle()->values();

        $pairs = [];
        $mixedCount = min($males->count(), $females->count());

        for ($i = 0; $i < $mixedCount; $i++) {
            $pairs[] = [$males[$i], $females[$i]];
        }

        $leftover = $males->slice($mixedCount)->values()
            ->concat($females->slice($mixedCount)->values())
            ->concat($rest)
            ->shuffle()
            ->values();

        for ($i = 0; $i < $leftover->count(); $i += 2) {
            $pairs[] = $leftover->has($i + 1)
                ? [$leftover[$i], $leftover[$i + 1]]
                : [$leftover[$i]];
        }

        shuffle($pairs);

        return $pairs;
    }
}
