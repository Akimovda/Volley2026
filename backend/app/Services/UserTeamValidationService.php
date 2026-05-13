<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\UserTeam;
use Illuminate\Support\Carbon;

class UserTeamValidationService
{
    /**
     * Validate team members against event requirements.
     * Returns array of ['user_id' => int, 'name' => string, 'issues' => string[]]
     * Empty array = all OK.
     */
    public function validateForEvent(UserTeam $team, Event $event, ?EventOccurrence $occurrence = null): array
    {
        $event->loadMissing('gameSettings');
        $team->load('members.user');

        $gs = $event->gameSettings;
        $direction = (string)($event->direction ?? 'classic');

        // Age policy
        $agePolicy = (string)($occurrence?->age_policy ?? $event->age_policy ?? 'adult');

        // Level requirements
        $levelMin = (int)($occurrence?->classic_level_min ?? $event->classic_level_min ?? 0);
        $levelMax = (int)($occurrence?->classic_level_max ?? $event->classic_level_max ?? 0);
        $beachMin = (int)($occurrence?->beach_level_min ?? $event->beach_level_min ?? 0);
        $beachMax = (int)($occurrence?->beach_level_max ?? $event->beach_level_max ?? 0);

        // Gender policy
        $genderPolicy = (string)($gs?->gender_policy ?? 'mixed_open');

        $errors = [];

        foreach ($team->members as $member) {
            $user = $member->user;
            if (!$user) continue;
            $issues = [];

            // Profile completeness
            if (empty($user->first_name) || empty($user->last_name)) {
                $issues[] = 'не заполнено имя/фамилия';
            }

            // Age policy
            if ($agePolicy === 'child' && $user->birth_date) {
                $age = Carbon::parse($user->birth_date)->age;
                if ($age >= 18) $issues[] = 'возраст не соответствует (мероприятие для детей)';
            } elseif ($agePolicy === 'adult' && $user->birth_date) {
                $age = Carbon::parse($user->birth_date)->age;
                if ($age < 18) $issues[] = 'возраст не соответствует (мероприятие для взрослых, нужно 18+)';
            }

            // Level check (classic)
            if ($direction === 'classic' && ($levelMin > 0 || $levelMax > 0)) {
                $ul = (int)($user->classic_level ?? 0);
                if ($levelMin > 0 && $ul > 0 && $ul < $levelMin) {
                    $issues[] = "уровень классики ({$ul}) ниже минимума ({$levelMin})";
                }
                if ($levelMax > 0 && $ul > 0 && $ul > $levelMax) {
                    $issues[] = "уровень классики ({$ul}) выше максимума ({$levelMax})";
                }
            }

            // Level check (beach)
            if ($direction === 'beach' && ($beachMin > 0 || $beachMax > 0)) {
                $ul = (int)($user->beach_level ?? 0);
                if ($beachMin > 0 && $ul > 0 && $ul < $beachMin) {
                    $issues[] = "уровень пляжа ({$ul}) ниже минимума ({$beachMin})";
                }
                if ($beachMax > 0 && $ul > 0 && $ul > $beachMax) {
                    $issues[] = "уровень пляжа ({$ul}) выше максимума ({$beachMax})";
                }
            }

            // Gender policy
            $gender = (string)($user->gender ?? '');
            if ($genderPolicy === 'only_male' && $gender === 'female') {
                $issues[] = 'только мужской состав';
            }
            if ($genderPolicy === 'only_female' && $gender === 'male') {
                $issues[] = 'только женский состав';
            }

            if ($issues) {
                $name = trim(($user->last_name ?? '') . ' ' . ($user->first_name ?? ''));
                if (!$name) $name = $user->name ?? ('User #' . $user->id);
                $errors[] = ['user_id' => (int)$user->id, 'name' => $name, 'issues' => $issues];
            }
        }

        return $errors;
    }

    /**
     * Check if total member count exceeds tournament limits.
     * Returns null if OK, or error string if exceeded.
     */
    public function checkTeamSize(UserTeam $team, Event $event): ?string
    {
        $event->loadMissing('tournamentSetting');
        $settings = $event->tournamentSetting;
        if (!$settings) return null;

        $teamSizeMin = (int)($settings->team_size_min ?? 0);
        $reserveMax  = (int)($settings->reserve_players_max ?? 0);

        if ($teamSizeMin <= 0) return null;

        $maxTotal = $teamSizeMin + $reserveMax;
        $memberCount = $team->members()->count();

        if ($memberCount > $maxTotal) {
            return "Состав команды ({$memberCount} чел.) превышает лимит турнира: {$teamSizeMin} основных + {$reserveMax} запасных = {$maxTotal} максимум.";
        }

        return null;
    }
}
