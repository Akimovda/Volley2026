<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\EventTeam;
use App\Models\EventTeamMember;
use App\Models\MatchPlayerStats;
use App\Models\MatchRallyEvent;
use App\Models\TournamentMatch;
use App\Models\TournamentStage;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * «Игра со статистикой»: матчи обычного мероприятия (format=game, events.collect_stats).
 * Живёт поверх турнирного движка (tournament_matches / match_player_stats / match_rally_events),
 * но со служебной стадией TYPE_FRIENDLY и командами, которые создаются под каждый матч
 * (составы на вечер меняются, организатор формирует их из записанных игроков).
 */
class FriendlyGameService
{
    /** Цвета команд: ключ => подпись. Имя команды = «цвет · №матча» (уникально в рамках occurrence). */
    public const COLORS = [
        'red'    => 'Красные',
        'blue'   => 'Синие',
        'green'  => 'Зелёные',
        'yellow' => 'Жёлтые',
        'white'  => 'Белые',
        'black'  => 'Чёрные',
    ];

    public function findStage(Event $event, int $occurrenceId): ?TournamentStage
    {
        return TournamentStage::query()
            ->where('event_id', $event->id)
            ->where('occurrence_id', $occurrenceId)
            ->where('type', TournamentStage::TYPE_FRIENDLY)
            ->first();
    }

    public function stageFor(Event $event, int $occurrenceId): TournamentStage
    {
        return $this->findStage($event, $occurrenceId) ?? TournamentStage::create([
            'event_id'      => $event->id,
            'occurrence_id' => $occurrenceId,
            'type'          => TournamentStage::TYPE_FRIENDLY,
            'name'          => 'Игра со статистикой',
            'sort_order'    => 1,
            'status'        => TournamentStage::STATUS_IN_PROGRESS,
            'config'        => ['match_format' => 'bo1', 'set_points' => 25, 'deciding_set_points' => 15],
        ]);
    }

    /** Значения формата матча по умолчанию (пока стадия ещё не создана). */
    public function defaultConfig(): array
    {
        return ['match_format' => 'bo1', 'set_points' => 25, 'deciding_set_points' => 15];
    }

    /**
     * Игроки, из которых можно формировать команды: подтверждённые регистрации occurrence
     * (в т.ч. добавленные организатором вручную — они тоже event_registrations).
     *
     * @return Collection<int, User>
     */
    public function roster(int $occurrenceId): Collection
    {
        $userIds = DB::table('event_registrations')
            ->where('occurrence_id', $occurrenceId)
            ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
            ->whereNull('cancelled_at')
            ->where('status', 'confirmed')
            ->pluck('user_id')
            ->unique()
            ->values();

        return User::query()->whereIn('id', $userIds)->get()
            ->sortBy(fn (User $u) => mb_strtolower(trim(($u->last_name ?? '') . ' ' . ($u->first_name ?? ''))))
            ->values();
    }

    /**
     * Позиции, на которые записаны игроки occurrence: user_id => position (classic: setter/outside/…, reserve; пляж: player).
     *
     * @return array<int,string>
     */
    public function positions(int $occurrenceId): array
    {
        return DB::table('event_registrations')
            ->where('occurrence_id', $occurrenceId)
            ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
            ->whereNull('cancelled_at')
            ->where('status', 'confirmed')
            ->whereNotNull('position')
            ->pluck('position', 'user_id')
            ->map(fn ($p) => (string) $p)
            ->all();
    }

    /**
     * @param int[] $homeUserIds
     * @param int[] $awayUserIds
     */
    public function createMatch(
        Event $event,
        int $occurrenceId,
        array $homeUserIds,
        array $awayUserIds,
        string $homeColor,
        string $awayColor,
    ): TournamentMatch {
        $homeUserIds = array_values(array_unique(array_map('intval', $homeUserIds)));
        $awayUserIds = array_values(array_unique(array_map('intval', $awayUserIds)));

        if (!$homeUserIds || !$awayUserIds) {
            throw new InvalidArgumentException('В каждой команде должен быть хотя бы один игрок.');
        }
        if (array_intersect($homeUserIds, $awayUserIds)) {
            throw new InvalidArgumentException('Игрок не может быть в обеих командах.');
        }
        if (!isset(self::COLORS[$homeColor], self::COLORS[$awayColor]) || $homeColor === $awayColor) {
            throw new InvalidArgumentException('Выберите разные цвета команд.');
        }

        $rosterIds = $this->roster($occurrenceId)->pluck('id')->all();
        foreach (array_merge($homeUserIds, $awayUserIds) as $uid) {
            if (!in_array($uid, $rosterIds, true)) {
                throw new InvalidArgumentException('В составе есть игрок, который не записан на это мероприятие.');
            }
        }

        return DB::transaction(function () use ($event, $occurrenceId, $homeUserIds, $awayUserIds, $homeColor, $awayColor) {
            $stage  = $this->stageFor($event, $occurrenceId);
            $number = (int) TournamentMatch::where('stage_id', $stage->id)->max('match_number') + 1;

            $home = $this->createTeam($event, $occurrenceId, $homeUserIds, self::COLORS[$homeColor] . ' · №' . $number, $homeColor);
            $away = $this->createTeam($event, $occurrenceId, $awayUserIds, self::COLORS[$awayColor] . ' · №' . $number, $awayColor);

            return TournamentMatch::create([
                'stage_id'      => $stage->id,
                'round'         => $number,
                'match_number'  => $number,
                'team_home_id'  => $home->id,
                'team_away_id'  => $away->id,
                'status'        => TournamentMatch::STATUS_SCHEDULED,
                'is_tiebreaker' => false,
            ]);
        });
    }

    /** Удалить матч вместе с его командами, статистикой и ходом матча. */
    public function deleteMatch(TournamentMatch $match): void
    {
        DB::transaction(function () use ($match) {
            $teamIds = array_filter([$match->team_home_id, $match->team_away_id]);

            MatchPlayerStats::where('match_id', $match->id)->delete();
            MatchRallyEvent::where('match_id', $match->id)->delete();
            $match->delete();

            // Команды служебные: создавались под этот матч (meta.friendly)
            foreach (EventTeam::whereIn('id', $teamIds)->get() as $team) {
                if (!empty($team->meta['friendly'])) {
                    EventTeamMember::where('event_team_id', $team->id)->delete();
                    $team->delete();
                }
            }
        });
    }

    /**
     * Итоги игрового вечера по игрокам (по завершённым матчам стадии).
     * Порядок: победы ↓, % побед ↓, разница очков ↓, набрано очков в статистике ↓.
     *
     * @return array{rows: array<int,array>, podium: array{type: string, items: array<int,array>}}
     */
    public function leaderboard(TournamentStage $stage): array
    {
        return $this->leaderboardForStages([$stage->id]);
    }

    /**
     * Итоги по серии: все игровые вечера мероприятия (опционально — не раньше $since).
     *
     * @return array{rows: array<int,array>, podium: array{type: string, items: array<int,array>}, evenings: int}
     */
    public function seriesLeaderboard(Event $event, ?\Carbon\CarbonInterface $since = null): array
    {
        $stageIds = TournamentStage::query()
            ->where('event_id', $event->id)
            ->where('type', TournamentStage::TYPE_FRIENDLY)
            ->when($since, fn ($q) => $q->whereIn('occurrence_id',
                DB::table('event_occurrences')->where('event_id', $event->id)->where('starts_at', '>=', $since)->select('id')))
            ->pluck('id')->all();

        $board = $this->leaderboardForStages($stageIds, false);
        $board['evenings'] = $stageIds
            ? TournamentMatch::whereIn('stage_id', $stageIds)->where('status', TournamentMatch::STATUS_COMPLETED)->distinct()->count('stage_id')
            : 0;

        return $board;
    }

    /** @param int[] $stageIds */
    private function leaderboardForStages(array $stageIds, bool $teamPodium = true): array
    {
        $matches = TournamentMatch::query()
            ->whereIn('stage_id', $stageIds)
            ->where('status', TournamentMatch::STATUS_COMPLETED)
            ->with(['teamHome.members', 'teamAway.members'])
            ->get();

        if ($matches->isEmpty()) {
            return ['rows' => [], 'podium' => ['type' => 'players', 'items' => []]];
        }

        $rows    = [];
        $lineups = []; // составы целиком: ключ = отсортированные id игроков
        $blank = fn () => [
            'games' => 0, 'wins' => 0, 'losses' => 0, 'sets_won' => 0, 'sets_lost' => 0,
            'pf' => 0, 'pa' => 0,
        ];

        foreach ($matches as $m) {
            foreach (['home', 'away'] as $side) {
                $team = $side === 'home' ? $m->teamHome : $m->teamAway;
                if (!$team) {
                    continue;
                }
                $other   = $side === 'home' ? 'away' : 'home';
                $teamId  = $team->id;
                $won     = (int) $m->winner_team_id === (int) $teamId;
                $setsFor = (int) ($m->{'sets_' . $side} ?? 0);
                $setsAg  = (int) ($m->{'sets_' . $other} ?? 0);
                $pf      = (int) ($m->{'total_points_' . $side} ?? 0);
                $pa      = (int) ($m->{'total_points_' . $other} ?? 0);

                $lineupKey = $team->members->pluck('user_id')->sort()->implode('-');
                $l = $lineups[$lineupKey] ?? [
                    'games' => 0, 'wins' => 0, 'losses' => 0, 'pf' => 0, 'pa' => 0,
                    'color' => $team->meta['color'] ?? 'white',
                    'user_ids' => $team->members->pluck('user_id')->all(),
                ];
                $l['games']++;
                $won ? $l['wins']++ : $l['losses']++;
                $l['pf'] += $pf;
                $l['pa'] += $pa;
                $lineups[$lineupKey] = $l;

                foreach ($team->members as $mem) {
                    $r = $rows[$mem->user_id] ?? $blank();
                    $r['games']++;
                    $won ? $r['wins']++ : $r['losses']++;
                    $r['sets_won'] += $setsFor;
                    $r['sets_lost'] += $setsAg;
                    $r['pf'] += $pf;
                    $r['pa'] += $pa;
                    $rows[$mem->user_id] = $r;
                }
            }
        }

        // Статистика игроков (если вводилась): суммы по матчам стадии
        $statRows = MatchPlayerStats::query()
            ->whereIn('match_id', $matches->pluck('id'))
            ->selectRaw('user_id, sum(points_scored) points_scored, sum(aces) aces, sum(kills) kills, sum(blocks) blocks, sum(digs) digs, sum(assists) assists')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $users = User::query()->whereIn('id', array_keys($rows))->get()->keyBy('id');

        $out = [];
        foreach ($rows as $uid => $r) {
            $s = $statRows->get($uid);
            $out[] = $r + [
                'user'          => $users->get($uid),
                'user_id'       => $uid,
                'win_rate'      => $r['games'] > 0 ? round($r['wins'] / $r['games'] * 100) : 0,
                'diff'          => $r['pf'] - $r['pa'],
                'points_scored' => (int) ($s->points_scored ?? 0),
                'aces'          => (int) ($s->aces ?? 0),
                'kills'         => (int) ($s->kills ?? 0),
                'blocks'        => (int) ($s->blocks ?? 0),
                'digs'          => (int) ($s->digs ?? 0),
                'assists'       => (int) ($s->assists ?? 0),
            ];
        }

        usort($out, fn ($a, $b) => [$b['wins'], $b['win_rate'], $b['diff'], $b['points_scored']]
            <=> [$a['wins'], $a['win_rate'], $a['diff'], $a['points_scored']]);

        // Лучшие игроки по набранным очкам (по данным статистики матчей)
        $scorers = array_values(array_filter($out, fn ($r) => $r['points_scored'] > 0));
        usort($scorers, fn ($a, $b) => [$b['points_scored'], $b['wins']] <=> [$a['points_scored'], $a['wins']]);

        return ['rows' => $out, 'top_scorers' => array_slice($scorers, 0, 5), 'podium' => $this->buildPodium($teamPodium ? $lineups : [], $out, $users)];
    }

    /**
     * Пьедестал: если за вечер играло не больше трёх разных составов (например, две постоянные команды) —
     * места занимают КОМАНДЫ (при двух командах — 1 и 2 место). Если составы менялись от матча к матчу —
     * пьедестал из трёх лучших игроков.
     *
     * @return array{type: string, items: array<int,array>}
     */
    private function buildPodium(array $lineups, array $playerRows, Collection $users): array
    {
        if (count($lineups) >= 2 && count($lineups) <= 3) {
            $items = array_map(function ($l) use ($users) {
                $games = max(1, $l['games']);

                return [
                    'color'    => $l['color'],
                    'label'    => self::COLORS[$l['color']] ?? $l['color'],
                    'users'    => collect($l['user_ids'])->map(fn ($id) => $users->get($id))->filter()->values(),
                    'wins'     => $l['wins'],
                    'games'    => $l['games'],
                    'win_rate' => (int) round($l['wins'] / $games * 100),
                    'diff'     => $l['pf'] - $l['pa'],
                ];
            }, array_values($lineups));

            usort($items, fn ($a, $b) => [$b['wins'], $b['win_rate'], $b['diff']] <=> [$a['wins'], $a['win_rate'], $a['diff']]);

            return ['type' => 'teams', 'items' => $items];
        }

        return ['type' => 'players', 'items' => array_slice($playerRows, 0, 3)];
    }

    /**
     * Состав одной команды по настройкам мероприятия: роль => сколько игроков.
     * Берётся из слотов позиций (max_slots / число команд), запасные не считаются.
     *
     * @return array<string,int>
     */
    public function botTeamComposition(Event $event): array
    {
        $slots = app(EventRoleSlotService::class)->getSlots($event)->where('role', '!=', 'reserve');
        $teams = max(1, (int) ($event->gameSettings?->teams_count ?? 2));

        $composition = [];
        foreach ($slots as $slot) {
            $composition[$slot->role] = max(1, intdiv((int) $slot->max_slots, $teams));
        }

        return $composition;
    }

    /**
     * Записывает на тур команду из ботов системы: нужное число игроков на каждую позицию,
     * только боты, проходящие требования мероприятия (возраст, уровень, гендерная политика/квота позиции),
     * ещё не записанные на тур, и только если на позициях есть свободные места.
     * Либо добавляется вся команда, либо ничего (транзакция).
     *
     * @return int[] id добавленных ботов (в порядке состава)
     * @throws InvalidArgumentException если полную команду собрать нельзя
     */
    public function addBotTeam(Event $event, EventOccurrence $occurrence, User $actor): array
    {
        $composition = $this->botTeamComposition($event);
        if (!$composition) {
            throw new InvalidArgumentException('Для мероприятия не настроены позиции — состав команды определить нельзя.');
        }

        $guard = app(EventRegistrationGuard::class);
        $occurrence->loadMissing('event.gameSettings');

        return DB::transaction(function () use ($event, $occurrence, $actor, $composition, $guard) {
            $added    = [];
            $busyIds  = DB::table('event_registrations')
                ->where('occurrence_id', $occurrence->id)
                ->pluck('user_id')->all();
            $isSingle = count($composition) === 1;

            foreach ($composition as $role => $need) {
                // Тот же advisory-lock, что у прямой записи и листа ожидания
                DB::select('select pg_advisory_xact_lock(?, ?)', [$occurrence->id, crc32($role) & 0x7fffffff]);

                $taken = (int) DB::table('event_registrations')
                    ->where('occurrence_id', $occurrence->id)
                    ->whereNull('cancelled_at')
                    ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
                    ->whereRaw("(status IS NULL OR status != 'cancelled')")
                    ->where('position', $role)
                    ->count();
                $max = (int) optional(app(EventRoleSlotService::class)->getSlots($event)->firstWhere('role', $role))->max_slots;
                if ($max > 0 && $taken + $need > $max) {
                    throw new InvalidArgumentException("Не хватает свободных мест на позиции «{$role}»: нужно {$need}, свободно " . max(0, $max - $taken) . '.');
                }

                $picked = 0;
                $candidates = User::query()
                    ->where('is_bot', true)
                    ->whereNull('deleted_at')
                    ->whereNotIn('id', array_merge($busyIds, $added))
                    ->inRandomOrder()
                    ->limit(200)
                    ->get();

                foreach ($candidates as $bot) {
                    if ($picked >= $need) {
                        break;
                    }
                    if (!$guard->checkStaticEligibility($bot, $occurrence, $role)->allowed) {
                        continue;
                    }
                    if (!$guard->checkGenderQuotaForUser($bot, $occurrence, null, $role)->allowed) {
                        continue;
                    }

                    $regId = DB::table('event_registrations')->insertGetId([
                        'event_id'      => $event->id,
                        'occurrence_id' => $occurrence->id,
                        'user_id'       => $bot->id,
                        'status'        => 'confirmed',
                        'position'      => $role,
                        'cancelled_at'  => null,
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ]);
                    if (\Illuminate\Support\Facades\Schema::hasTable('event_registration_logs')) {
                        DB::table('event_registration_logs')->insert([
                            'registration_id' => $regId,
                            'event_id'        => $event->id,
                            'occurrence_id'   => $occurrence->id,
                            'user_id'         => $bot->id,
                            'actor_id'        => $actor->id,
                            'action'          => 'registered',
                            'created_at'      => now(),
                        ]);
                    }
                    $added[] = $bot->id;
                    $picked++;
                }

                if ($picked < $need) {
                    throw new InvalidArgumentException("Не нашлось достаточно подходящих ботов для позиции «{$role}» (нужно {$need}, подошло {$picked}) — проверьте уровень/возраст/пол в требованиях мероприятия.");
                }
            }

            return $added;
        });
    }

    /** @param int[] $userIds */
    private function createTeam(Event $event, int $occurrenceId, array $userIds, string $name, string $color): EventTeam
    {
        $team = EventTeam::create([
            'event_id'        => $event->id,
            'occurrence_id'   => $occurrenceId,
            'captain_user_id' => $userIds[0],
            'name'            => $name,
            'team_kind'       => $event->direction === 'beach' ? 'beach_pair' : 'classic_team',
            'status'          => 'approved',
            'invite_code'     => Str::upper(Str::random(10)),
            'is_complete'     => true,
            'confirmed_at'    => now(),
            'meta'            => ['friendly' => true, 'color' => $color],
        ]);

        foreach ($userIds as $i => $uid) {
            EventTeamMember::create([
                'event_team_id'       => $team->id,
                'user_id'             => $uid,
                'role_code'           => 'player',
                'team_role'           => $i === 0 ? 'captain' : 'player',
                'confirmation_status' => 'confirmed',
                'position_order'      => $i + 1,
                'joined_at'           => now(),
                'confirmed_at'        => now(),
            ]);
        }

        return $team;
    }
}
