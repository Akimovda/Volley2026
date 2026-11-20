<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\EventRoleSlot;
use Illuminate\Support\Collection;

/**
 * Ёмкость КОНКРЕТНОЙ даты (авто увеличение кворума, docs/auto-quorum.md, этап 2).
 *
 * Главный инвариант: пока дата не расширена (event_occurrences.quorum_teams_count IS NULL), все методы
 * возвращают РОВНО то, что потребители брали до этапа 2:
 *   - лимит роли — event_role_slots.max_slots мероприятия;
 *   - maxPlayers() — event_occurrences.max_players ?? event_game_settings.max_players (как effectiveMaxPlayers());
 *   - seriesMaxPlayers() — event_game_settings.max_players (для тех, кто раньше читал только уровень мероприятия:
 *     анонс, пляжная ветка WaitlistService).
 * Новый расчёт (роли на команду из GameCalculator × число команд даты) включается ТОЛЬКО у расширенной даты.
 *
 * Не путать с OccurrenceCapacityService (вместимость площадки/брони).
 */
class OccurrenceLimitService
{
    public function isExpanded(EventOccurrence $occ): bool
    {
        return $occ->quorum_teams_count !== null && (int) $occ->quorum_teams_count > 0;
    }

    /** Число команд даты: quorum_teams_count ?? event_occurrence_game_settings.teams_count ?? event_game_settings.teams_count. */
    public function teamsFor(EventOccurrence $occ): int
    {
        if ($this->isExpanded($occ)) {
            return (int) $occ->quorum_teams_count;
        }

        return (int) ($occ->effectiveGameSettings()->teams_count ?? 0);
    }

    /** Число команд мероприятия (база, от которой считается расширение и масштабируются квоты). */
    public function baseTeams(EventOccurrence $occ): int
    {
        return (int) ($this->event($occ)?->gameSettings?->teams_count ?? 0);
    }

    /**
     * Роли на ОДНУ команду/пару по схеме даты: роль => игроков.
     * Классика — по схеме (4x2/4x4/5x1/5x1 с либеро); «без амплуа» — player = размер команды;
     * пляж — player = размер команды (2x2 → 2), «По амплуа» — blocker 1 + defender 1.
     *
     * @return array<string,int>
     */
    public function perTeamRoles(EventOccurrence $occ): array
    {
        [$roles] = $this->scheme($occ);

        return $roles;
    }

    /** Игроков в одной команде/паре по схеме даты. */
    public function teamSize(EventOccurrence $occ): int
    {
        [, $size] = $this->scheme($occ);

        return $size;
    }

    /**
     * Лимиты всех ролей-слотов мероприятия для даты: роль => мест.
     * Не расширена — max_slots как есть. Расширена — роли на команду × команд даты; роль, которой нет в схеме
     * (рассинхрон данных, 'reserve' как слот), — max_slots без изменений.
     *
     * @param  Collection|null  $slots  слоты мероприятия (EventRoleSlot), если уже загружены вызывающим
     * @return array<string,int>
     */
    public function roleLimits(EventOccurrence $occ, ?Collection $slots = null): array
    {
        $slots ??= $this->slots($occ);

        $limits = [];
        foreach ($slots as $slot) {
            $limits[(string) $slot->role] = (int) $slot->max_slots;
        }

        if (!$this->isExpanded($occ)) {
            return $limits;
        }

        $perTeam = $this->perTeamRoles($occ);
        $teams   = $this->teamsFor($occ);
        foreach ($limits as $role => $max) {
            if ($role !== 'reserve' && isset($perTeam[$role])) {
                $limits[$role] = $perTeam[$role] * $teams;
            }
        }

        return $limits;
    }

    /** Лимит одной роли для даты (0 — роли нет у мероприятия). */
    public function roleLimit(EventOccurrence $occ, string $role, ?Collection $slots = null): int
    {
        return $this->roleLimits($occ, $slots)[$role] ?? 0;
    }

    /** Основной состав даты. Не расширена — как EventOccurrence::effectiveMaxPlayers() до этапа 2. */
    public function maxPlayers(EventOccurrence $occ): int
    {
        if ($this->isExpanded($occ)) {
            return $this->teamSize($occ) * $this->teamsFor($occ);
        }

        return (int) ($occ->max_players ?? $this->event($occ)?->gameSettings?->max_players ?? 0);
    }

    /**
     * Основной состав для потребителей, которые до этапа 2 читали ТОЛЬКО уровень мероприятия
     * (анонс, пляжная ветка WaitlistService): не расширена — event_game_settings.max_players,
     * расширена — ёмкость даты. Переопределение max_players даты здесь по-прежнему не учитывается
     * (странность №3/№4 этапа 1 — не меняем поведение без решения владельца).
     */
    public function seriesMaxPlayers(EventOccurrence $occ): int
    {
        if ($this->isExpanded($occ)) {
            return $this->maxPlayers($occ);
        }

        return (int) ($this->event($occ)?->gameSettings?->max_players ?? 0);
    }

    /** Лимит запасных даты. Сейчас от даты не зависит (не масштабируется при расширении). */
    public function reserveMax(EventOccurrence $occ): int
    {
        return (int) ($this->event($occ)?->gameSettings?->reserve_players_max ?? 0);
    }

    // ------------------------------------------------------------------

    private function event(EventOccurrence $occ): ?Event
    {
        return $occ->event ?: $occ->loadMissing('event')->event;
    }

    private function slots(EventOccurrence $occ): Collection
    {
        return EventRoleSlot::where('event_id', $occ->event_id)->get();
    }

    /** @return array{0: array<string,int>, 1: int} [роли на команду, размер команды] */
    private function scheme(EventOccurrence $occ): array
    {
        $event = $this->event($occ);
        if (!$event) {
            return [[], 0];
        }

        $gs      = $occ->effectiveGameSettings();
        $subtype = (string) ($gs->subtype ?? '');
        if (($event->direction ?? 'classic') === 'beach' && $subtype === '4x4') {
            $subtype = '4x4_beach'; // ключ пляжной 4x4 в GameCalculator (классическая — '4x4')
        }

        $calc = GameCalculator::calculate($subtype, $gs->libero_mode, 1, $event->registersByBeachRoles());
        $size = (int) $calc['team_size'];

        if ($event->registersWithoutPositions() && $size > 0) {
            return [['player' => $size], $size];
        }

        return [array_map('intval', (array) $calc['roles']), $size];
    }
}
