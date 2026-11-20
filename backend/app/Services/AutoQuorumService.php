<?php

namespace App\Services;

use App\Jobs\NotifyOrganizerQuorumExpandedJob;
use App\Jobs\RefreshOccurrenceAnnouncementJob;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\EventRegistration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Авто увеличение кворума (docs/auto-quorum.md, этап 3).
 *
 * Если на конкретную дату игры записалось достаточно людей (основа + запасные + лист ожидания), дата
 * получает ещё одну команду (пару): event_occurrences.quorum_teams_count растёт на ступень, ёмкость даты
 * считает OccurrenceLimitService, новые места заполняются из листа ожидания штатным
 * WaitlistService::autoBookNext по ролям новых слотов. Только рост; серию и слоты мероприятия не трогает.
 *
 * Вызывать ТОЛЬКО после коммита записи (safeMaybeExpand) — ошибка расширения не должна ломать запись.
 */
class AutoQuorumService
{
    /**
     * Второй ключ pg_advisory_xact_lock(occurrence_id, key) для пересчёта кворума даты.
     * Отрицательный — не пересекается с ключами записи на позицию (crc32(position) & 0x7fffffff ≥ 0, пустая позиция — 0).
     */
    public const QUORUM_KEY = -20261120;

    public const FORMATS = ['game', 'training_game'];

    public function __construct(private OccurrenceLimitService $limits)
    {
    }

    // ------------------------------------------------------------------ настройки

    /** Можно ли включать функцию для такого мероприятия (формат/режим/направление/статистика). Ключ ошибки — null, если можно. */
    public function ineligibilityReason(string $format, string $direction, string $registrationMode, bool $collectStats): ?string
    {
        if (!in_array($format, self::FORMATS, true)) {
            return 'format';
        }
        if (!in_array($direction, ['classic', 'beach'], true)) {
            return 'format';
        }
        if (!in_array($registrationMode, ['single', ''], true)) {
            return 'mode';
        }
        if ($collectStats) {
            return 'stats';
        }

        return null;
    }

    /**
     * Валидация настроек из формы создания/редактирования. Возвращает [поле => сообщение].
     *
     * @param  array  $data  поля формы (auto_quorum_enabled, auto_quorum_players_per_team, auto_quorum_max_teams, teams_count, …)
     * @param  Event|null  $event  при редактировании — для проверки уже расширенных будущих дат
     */
    public function validateSettings(array $data, string $format, string $direction, string $registrationMode, ?Event $event = null): array
    {
        if (empty($data['auto_quorum_enabled'])) {
            return [];
        }

        $errors = [];
        $reason = $this->ineligibilityReason($format, $direction, $registrationMode, !empty($data['collect_stats']));
        if ($reason !== null) {
            $errors['auto_quorum_enabled'] = __('events.auto_quorum_err_' . $reason);

            return $errors;
        }

        $sfx   = $direction === 'beach' ? '_pair' : '';   // у пляжа — «пар», у классики — «команд»
        $teams = (int) ($data['teams_count'] ?? $event?->gameSettings?->teams_count ?? 2);
        $max   = $data['auto_quorum_max_teams'] ?? null;
        if ($max === null || $max === '') {
            $errors['auto_quorum_max_teams'] = __('events.auto_quorum_err_max_required' . $sfx);
        } elseif ((int) $max < $teams) {
            $errors['auto_quorum_max_teams'] = __('events.auto_quorum_err_max_lt_teams' . $sfx, ['teams' => $teams]);
        } elseif ((int) $max > 50) {
            $errors['auto_quorum_max_teams'] = __('events.auto_quorum_err_max_too_big' . $sfx);
        } elseif ($event) {
            // будущая дата с наибольшим расширением (при равенстве — самая поздняя)
            $expanded = DB::table('event_occurrences')
                ->where('event_id', $event->id)
                ->where('starts_at', '>', now('UTC'))
                ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
                ->whereNotNull('quorum_teams_count')
                ->orderByDesc('quorum_teams_count')->orderByDesc('starts_at')
                ->first(['starts_at', 'timezone', 'quorum_teams_count']);
            if ($expanded && (int) $max < (int) $expanded->quorum_teams_count) {
                $tz = $expanded->timezone ?: ($event->timezone ?: 'Europe/Moscow');
                $errors['auto_quorum_max_teams'] = __('events.auto_quorum_err_max_lt_expanded', [
                    'date'  => Carbon::parse($expanded->starts_at, 'UTC')->setTimezone($tz)->format('d.m.Y'),
                    'teams' => (int) $expanded->quorum_teams_count,
                    'unit'  => __($direction === 'beach' ? 'events.auto_quorum_unit_pairs' : 'events.auto_quorum_unit_teams'),
                ]);
            }
        }

        $per = $data['auto_quorum_players_per_team'] ?? null;
        if ($per !== null && $per !== '' && ((int) $per < 1 || (int) $per > 20)) {
            $errors['auto_quorum_players_per_team'] = __('events.auto_quorum_err_per_team' . $sfx);
        }

        return $errors;
    }

    /** Игроков на команду/пару по умолчанию: классика — размер команды − 1 (4-2/5-1 → 5, с либеро → 6), пляж — размер пары. */
    public static function defaultPlayersPerTeam(string $direction, ?string $subtype, ?string $liberoMode): int
    {
        if ($direction === 'beach') {
            $key = ($subtype ?: '2x2') === '4x4' ? '4x4_beach' : ($subtype ?: '2x2');
            $size = (int) (GameCalculator::calculate($key, null, 1)['team_size'] ?? 2);

            return max(1, $size ?: 2);
        }
        $size = (int) (GameCalculator::calculate($subtype ?: '4x2', $liberoMode, 1)['team_size'] ?? 6);

        return max(1, ($size ?: 6) - 1);
    }

    /** perTeam мероприятия (настройка или значение по умолчанию). */
    public function playersPerTeam(Event $event): int
    {
        $gs = $event->gameSettings;
        $per = (int) ($gs?->auto_quorum_players_per_team ?? 0);

        return $per > 0 ? $per : self::defaultPlayersPerTeam((string) $event->direction, $gs?->subtype, $gs?->libero_mode);
    }

    /**
     * Пороги открытия команд: [номер команды => записавшихся]. Команда k открывается при N ≥ k × perTeam.
     * Для текста в форме: «3-я команда откроется при 15 записавшихся, 4-я — при 20».
     *
     * @return array<int,int>
     */
    public static function thresholds(int $baseTeams, int $maxTeams, int $perTeam): array
    {
        $out = [];
        for ($k = $baseTeams + 1; $k <= $maxTeams; $k++) {
            $out[$k] = $k * $perTeam;
        }

        return $out;
    }

    /** Целевое число команд: min(max, max(текущие, floor(N / perTeam))). Только рост. */
    public static function targetTeams(int $currentTeams, int $registered, int $perTeam, int $maxTeams): int
    {
        if ($perTeam <= 0) {
            return $currentTeams;
        }

        return min(max($maxTeams, $currentTeams), max($currentTeams, intdiv($registered, $perTeam)));
    }

    // ------------------------------------------------------------------ расширение

    /** Включена ли функция и подходит ли дата прямо сейчас (не началась, регистрация открыта). */
    public function isActiveFor(EventOccurrence $occ): bool
    {
        $event = $occ->event;
        $gs    = $event?->gameSettings;
        if (!$event || !$gs || !$gs->auto_quorum_enabled || (int) ($gs->auto_quorum_max_teams ?? 0) <= 0) {
            return false;
        }
        if ($this->ineligibilityReason((string) $event->format, (string) $event->direction, (string) ($event->registration_mode ?? ''), (bool) $event->collect_stats) !== null) {
            return false;
        }
        if ($occ->is_cancelled || $occ->cancelled_at) {
            return false;
        }
        $now = Carbon::now('UTC');
        if (!$occ->starts_at || $now->gte(Carbon::parse($occ->starts_at, 'UTC'))) {
            return false;
        }
        if (!$occ->effectiveAllowRegistration()) {
            return false;
        }
        $regStarts = $occ->effectiveRegistrationStartsAt();
        if ($regStarts && $now->lt($regStarts)) {
            return false;
        }
        $regEnds = $occ->effectiveRegistrationEndsAt();
        if ($regEnds && $now->gte($regEnds)) {
            return false;
        }

        return true;
    }

    /** N = активные в основе + активные запасные + лист ожидания этой даты. */
    public function countRegistered(int $occurrenceId): int
    {
        $regs = (int) DB::table('event_registrations')
            ->where('occurrence_id', $occurrenceId)
            ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
            ->whereNull('cancelled_at')
            ->whereRaw("(status IS NULL OR status != 'cancelled')")
            ->count();
        $waitlist = (int) DB::table('occurrence_waitlist')->where('occurrence_id', $occurrenceId)->count();

        return $regs + $waitlist;
    }

    /** То же, что maybeExpand(), но никогда не бросает: ошибка — в лог и Sentry, запись не страдает. */
    public function safeMaybeExpand(?EventOccurrence $occ): ?array
    {
        if (!$occ) {
            return null;
        }
        try {
            return $this->maybeExpand($occ);
        } catch (\Throwable $e) {
            Log::error('AutoQuorum: maybeExpand failed', ['occurrence_id' => $occ->id, 'error' => $e->getMessage()]);
            report($e);

            return null;
        }
    }

    /**
     * Расширить дату, если набралось людей. Возвращает null (ничего не сделано) или
     * ['from' => int, 'to' => int, 'registered' => int, 'promoted' => int[], 'log_id' => int].
     */
    public function maybeExpand(EventOccurrence $occ): ?array
    {
        $occ = EventOccurrence::with(['event.gameSettings', 'gameSettingsOverride'])->find($occ->id);
        if (!$occ || !$this->isActiveFor($occ)) {
            return null;
        }

        $event  = $occ->event;
        $per    = $this->playersPerTeam($event);
        $maxT   = (int) $event->gameSettings->auto_quorum_max_teams;

        // 1. Под замком даты: пересчёт и условный UPDATE (повторный/параллельный вызов не расширит дважды)
        $result = DB::transaction(function () use ($occ, $per, $maxT) {
            DB::select('SELECT pg_advisory_xact_lock(?, ?)', [$occ->id, self::QUORUM_KEY]);

            $fresh   = EventOccurrence::with(['event.gameSettings', 'gameSettingsOverride'])->find($occ->id);
            $current = $this->limits->teamsFor($fresh);
            $baseNow = $fresh->quorum_teams_count === null ? $current : (int) $fresh->quorum_teams_count;
            $n       = $this->countRegistered((int) $fresh->id);
            $target  = self::targetTeams($current, $n, $per, $maxT);
            if ($target <= $current) {
                return null;
            }

            $teamSize = $this->limits->teamSize($fresh);
            $updated = DB::update(
                'UPDATE event_occurrences SET quorum_teams_count = ?, quorum_expanded_at = ?, max_players = ?, updated_at = ?
                 WHERE id = ? AND COALESCE(quorum_teams_count, ?) = ?',
                [$target, now('UTC'), $teamSize > 0 ? $teamSize * $target : null, now(), $fresh->id, $baseNow, $baseNow]
            );
            if ($updated !== 1) {
                return null;
            }

            $logId = DB::table('event_occurrence_quorum_log')->insertGetId([
                'occurrence_id'     => $fresh->id,
                'from_teams'        => $current,
                'to_teams'          => $target,
                'total_registered'  => $n,
                'promoted_user_ids' => json_encode([]),
                'created_at'        => now('UTC'),
            ]);

            return ['from' => $current, 'to' => $target, 'registered' => $n, 'log_id' => (int) $logId];
        });

        if ($result === null) {
            return null;
        }

        Log::info('AutoQuorum: date expanded', ['occurrence_id' => $occ->id] + $result);

        // 2. После коммита — перевод из листа ожидания на новые места, по ролям новых слотов
        $result['promoted'] = $this->promoteFromWaitlist($occ->id, $result['to'] - $result['from']);
        if ($result['promoted']) {
            DB::table('event_occurrence_quorum_log')->where('id', $result['log_id'])
                ->update(['promoted_user_ids' => json_encode($result['promoted'])]);
        }

        // 3. Анонс («из M» растёт) и уведомление организатору
        RefreshOccurrenceAnnouncementJob::dispatch((int) $occ->id)->afterCommit();
        NotifyOrganizerQuorumExpandedJob::dispatch((int) $occ->id, $result['from'], $result['to'], count($result['promoted']))
            ->onQueue('default')->afterCommit();

        return $result;
    }

    /**
     * Для каждой добавленной команды по каждой роли (роль × добавленных команд) — autoBookNext.
     * Пляж «По амплуа»: +1 blocker +1 defender на пару; пляж/без амплуа — роль player.
     * Кому подходящей роли нет — остаются в листе ожидания.
     *
     * @return int[] переведённые user_id
     */
    private function promoteFromWaitlist(int $occurrenceId, int $addedTeams): array
    {
        $occ = EventOccurrence::with(['event.gameSettings', 'gameSettingsOverride'])->find($occurrenceId);
        if (!$occ || $addedTeams <= 0) {
            return [];
        }
        $waitingIds = DB::table('occurrence_waitlist')->where('occurrence_id', $occurrenceId)->pluck('user_id')->map(fn ($v) => (int) $v)->all();
        if (!$waitingIds) {
            return [];
        }

        $waitlist = app(WaitlistService::class);
        foreach ($this->limits->perTeamRoles($occ) as $role => $perTeam) {
            for ($i = 0; $i < $perTeam * $addedTeams; $i++) {
                if (!$waitlist->autoBookNext($occ, $role)) {
                    break; // на эту роль в очереди никого подходящего / места кончились
                }
            }
        }

        return EventRegistration::query()
            ->where('occurrence_id', $occurrenceId)
            ->whereIn('user_id', $waitingIds)
            ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
            ->whereNull('cancelled_at')
            ->pluck('user_id')->map(fn ($v) => (int) $v)->values()->all();
    }

    /** Все будущие даты мероприятия (после сохранения настроек). Никогда не бросает. */
    public function expandEventDates(Event $event): int
    {
        $expanded = 0;
        $ids = DB::table('event_occurrences')->where('event_id', $event->id)->where('starts_at', '>', now('UTC'))
            ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')->orderBy('starts_at')->limit(200)->pluck('id');
        foreach ($ids as $id) {
            if ($this->safeMaybeExpand(EventOccurrence::find($id))) {
                $expanded++;
            }
        }

        return $expanded;
    }

    /** Страховочный проход: будущие даты мероприятий с включённой функцией. Возвращает число расширенных дат. */
    public function sweep(int $limit = 500): int
    {
        $ids = DB::table('event_occurrences as o')
            ->join('events as e', 'e.id', '=', 'o.event_id')
            ->join('event_game_settings as g', 'g.event_id', '=', 'e.id')
            ->where('g.auto_quorum_enabled', true)
            ->whereIn('e.format', self::FORMATS)
            ->where('o.starts_at', '>', now('UTC'))
            ->whereRaw('(o.is_cancelled IS NULL OR o.is_cancelled = false)')
            ->whereNull('o.cancelled_at')
            ->orderBy('o.starts_at')
            ->limit($limit)
            ->pluck('o.id');

        $expanded = 0;
        foreach ($ids as $id) {
            if ($this->safeMaybeExpand(EventOccurrence::find($id))) {
                $expanded++;
            }
        }

        return $expanded;
    }
}
