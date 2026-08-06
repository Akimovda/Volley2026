<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventTeam;
use App\Models\User;
use Carbon\Carbon;

/**
 * Единая точка проверки соответствия игрока требованиям мероприятия.
 *
 * Используется при:
 *  - добавлении игрока в команду (TournamentTeamService::inviteOrJoinMember / confirmMember)
 *  - запросе на вступление (TournamentTeamService::joinRequest)
 *  - подаче заявки команды (TournamentTeamService::submitApplication, в т.ч. early)
 *
 * Возвращает массив строк-issues. Пустой массив = соответствует.
 */
class MemberEligibilityService
{
    /**
     * Проверка одного игрока против ограничений мероприятия.
     */
    /**
     * @param bool $includeGender false — пропустить пункт 4 (пол). Используется
     *        TournamentTeamService::inviteOrJoinMember() при добавлении игрока
     *        ОРГАНИЗАТОРОМ (addMemberByOrganizer) — по решению задачи гендерное
     *        несоответствие для этого пути не блокирует, только предупреждает
     *        (см. genderIssueFor() + TournamentTeamController::addMemberByOrganizer()).
     *        Для остальных вызовов (самостоятельное вступление, заявка команды) —
     *        includeGender=true, поведение не меняется.
     */
    public function checkMember(User $user, Event $event, bool $includeGender = true): array
    {
        $issues = [];

        $userLabel = trim(($user->last_name ?? '') . ' ' . ($user->first_name ?? '')) ?: ($user->name ?? "#{$user->id}");

        // 1. Уровень — от direction (classic / beach)
        $direction = (string) ($event->direction ?? 'classic');
        $levelField = $direction === 'beach' ? 'beach_level' : 'classic_level';
        $userLevel = $user->{$levelField} ?? null;
        $lvMin = $direction === 'beach' ? $event->beach_level_min : $event->classic_level_min;
        $lvMax = $direction === 'beach' ? $event->beach_level_max : $event->classic_level_max;

        if (!is_null($userLevel) && !is_null($lvMin) && (int) $userLevel < (int) $lvMin) {
            $issues[] = "{$userLabel}: уровень ниже минимального ({$userLevel} < {$lvMin})";
        }
        if (!is_null($userLevel) && !is_null($lvMax) && (int) $userLevel > (int) $lvMax) {
            $issues[] = "{$userLabel}: уровень выше максимального ({$userLevel} > {$lvMax})";
        }

        // 2. Возраст
        $agePolicy = (string) ($event->age_policy ?? 'any');
        if (in_array($agePolicy, ['adult', 'child'], true)) {
            $birth = $user->birth_date ? Carbon::parse($user->birth_date) : null;
            $age = $birth ? $birth->diffInYears(now()) : null;

            if ($agePolicy === 'adult' && (!is_null($age) && $age < 18)) {
                $issues[] = "{$userLabel}: только для взрослых (18+)";
            }
            if ($agePolicy === 'child') {
                $minAge = (int) ($event->child_age_min ?? 0);
                $maxAge = (int) ($event->child_age_max ?? 0);
                if ($minAge && !is_null($age) && $age < $minAge) {
                    $issues[] = "{$userLabel}: возраст ниже допустимого ({$age} < {$minAge})";
                }
                if ($maxAge && !is_null($age) && $age > $maxAge) {
                    $issues[] = "{$userLabel}: возраст выше допустимого ({$age} > {$maxAge})";
                }
            }
        }

        // 3. Профиль заполнен (если событие требует персональные данные)
        if (!empty($event->requires_personal_data)) {
            $missing = [];
            if (empty($user->first_name)) $missing[] = 'имя';
            if (empty($user->last_name)) $missing[] = 'фамилия';
            if (empty($user->phone)) $missing[] = 'телефон';
            if (empty($user->birth_date)) $missing[] = 'дата рождения';
            if ($missing) {
                $issues[] = "{$userLabel}: не заполнено в профиле — " . implode(', ', $missing);
            }
        }

        // 4. Пол игрока (для policy: only_male / only_female).
        // Лимит mixed_limited / mixed_5050 — это правило команды, проверяется отдельно
        // в TournamentTeamService::validateTeamGender.
        if ($includeGender) {
            $genderIssue = $this->genderIssueFor($user, $event);
            if ($genderIssue) {
                $issues[] = "{$userLabel}: {$genderIssue}";
            }
        }

        return $issues;
    }

    /**
     * Только гендерная часть проверки (пункт 4 checkMember(), без префикса с
     * именем игрока) — переиспользуется checkMember() и вызывающим кодом,
     * которому нужно ПРЕДУПРЕДИТЬ о несоответствии, а не заблокировать
     * (TournamentTeamController::addMemberByOrganizer()). null = соответствует.
     */
    public function genderIssueFor(User $user, Event $event): ?string
    {
        $gameSettings = $event->relationLoaded('gameSettings')
            ? $event->gameSettings
            : \App\Models\EventGameSetting::where('event_id', $event->id)->first();

        $policy = (string) ($gameSettings?->gender_policy ?? 'mixed_open');
        $userGender = $user->gender ?? null;

        if ($policy === 'only_male' && $userGender === 'f') {
            return 'турнир только для мужчин';
        }
        if ($policy === 'only_female' && $userGender === 'm') {
            return 'турнир только для женщин';
        }

        return null;
    }

    /**
     * Проверка всех confirmed-игроков команды против события.
     * Возвращает плоский массив issues.
     */
    public function checkTeamMembers(EventTeam $team): array
    {
        $event = $team->event ?: $team->event()->first();
        if (!$event) {
            return [];
        }

        $issues = [];
        $members = $team->members()
            ->whereIn('confirmation_status', ['confirmed', 'self'])
            ->with('user')
            ->get();

        foreach ($members as $member) {
            if (!$member->user) continue;
            $issues = array_merge($issues, $this->checkMember($member->user, $event));
        }

        return $issues;
    }
}
