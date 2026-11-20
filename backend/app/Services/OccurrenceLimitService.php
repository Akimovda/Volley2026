<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\EventRoleSlot;
use Illuminate\Support\Collection;

/**
 * Ёмкость КОНКРЕТНОЙ даты (авто увеличение кворума, docs/auto-quorum.md).
 *
 * Правило (решение владельца 2026-11-20, этап 3, шаг A): ёмкость даты задаётся ТОЛЬКО числом команд.
 * Число команд даты — quorum_teams_count (авторасширение) ?? «команд на этой дате» из формы даты
 * (event_occurrence_game_settings.teams_count) ?? teams_count мероприятия.
 *   - У даты со «своим» числом команд (hasOwnTeams) лимиты ролей и основной состав считаются по схеме:
 *     роли на команду (GameCalculator) × команд даты.
 *   - Иначе — как у мероприятия: max_slots слотов; основной состав — occurrence.max_players ?? мероприятия
 *     (occurrence.max_players теперь производное: размер команды × команд даты, см. derivedMaxPlayers()).
 *
 * Не путать с OccurrenceCapacityService (вместимость площадки/брони, автозапись по абонементу/Premium).
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

    /**
     * У даты своё число команд, отличное от мероприятия: расширена авто-кворумом или в форме даты задано
     * «команд на этой дате». Тогда лимиты ролей считаются по схеме, а не берутся из max_slots мероприятия.
     */
    public function hasOwnTeams(EventOccurrence $occ): bool
    {
        if ($this->isExpanded($occ)) {
            return true;
        }
        $override = $occ->gameSettingsOverride?->teams_count;

        return $override !== null && (int) $override > 0 && (int) $override !== $this->baseTeams($occ);
    }

    /**
     * Основной состав даты по правилу «ёмкость = число команд»: размер команды × команд даты.
     * null — схема к мероприятию неприменима (мастер-класс, King Beach, командный турнир и т.п.) — тогда
     * вызывающий код оставляет прежнее значение (max_players мероприятия).
     */
    public function derivedMaxPlayers(EventOccurrence $occ): ?int
    {
        $event = $this->event($occ);
        if (!$event || !in_array((string) $event->format, ['game', 'training_game', 'training', 'training_pro_am', 'camp', 'coach_student'], true)) {
            return null;
        }
        if (in_array((string) $event->registration_mode, ['king_beach', 'team_classic', 'team_beach'], true)) {
            return null;
        }
        $size  = $this->teamSize($occ);
        $teams = $this->teamsFor($occ);

        return ($size > 0 && $teams > 0) ? $size * $teams : null;
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
     * Своего числа команд нет — max_slots как есть. Есть (hasOwnTeams) — роли на команду × команд даты; роль, которой нет в схеме
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

        if (!$this->hasOwnTeams($occ)) {
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

    /**
     * Основной состав даты. Расширена — размер команды × команд даты; иначе occurrence.max_players ?? мероприятия
     * (occurrence.max_players производное — пересчитывается при сохранении даты и правке серии).
     */
    public function maxPlayers(EventOccurrence $occ): int
    {
        if ($this->isExpanded($occ)) {
            return $this->teamSize($occ) * $this->teamsFor($occ);
        }

        return (int) ($occ->max_players ?? $this->event($occ)?->gameSettings?->max_players ?? 0);
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
