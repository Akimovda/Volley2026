<?php

namespace App\Services;

use App\Models\Event;
use App\Models\User;

class EventRegistrationRequirements
{
    public function missing(User $user, Event $event): array
    {
        $missing = [];

        if ($event->requires_personal_data) {
            $missing = array_merge($missing, $this->missingPersonalData($user));
        }

        if (!is_null($event->classic_level_min) && is_null($user->classic_level)) {
            $missing[] = 'classic_level';
        }

        if (!is_null($event->beach_level_min) && is_null($user->beach_level)) {
            $missing[] = 'beach_level';
        }

        return array_values(array_unique($missing));
    }

    public function ensureEligible(User $user, Event $event): void
    {
        if (!is_null($event->classic_level_min) && !is_null($user->classic_level)) {
            if ($user->classic_level < $event->classic_level_min) {
                abort(403, 'Ваш уровень в классике ниже требуемого.');
            }
        }

        if (!is_null($event->beach_level_min) && !is_null($user->beach_level)) {
            if ($user->beach_level < $event->beach_level_min) {
                abort(403, 'Ваш уровень в пляже ниже требуемого.');
            }
        }
    }

    private function missingPersonalData(User $user): array
    {
        $missing = [];

        if (empty($user->last_name) || empty($user->first_name)) {
            $missing[] = 'full_name';
        }

        if (empty($user->patronymic)) {
            $missing[] = 'patronymic';
        }

        if (empty($user->phone)) {
            $missing[] = 'phone';
        }

        if (empty($user->city_id)) {
            $missing[] = 'city';
        }

        if (empty($user->birth_date)) {
            $missing[] = 'birth_date';
        }

        return $missing;
    }
}
