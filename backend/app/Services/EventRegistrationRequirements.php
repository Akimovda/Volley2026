<?php

namespace App\Services;

use App\Models\Event;
use App\Models\User;

class EventRegistrationRequirements
{
    /**
     * Returns a list of missing profile keys required to join the event.
     *
     * @return array<int, string>
     */
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

    /**
     * Throws 403 if user is not eligible due to level being lower than required.
     */
    public function ensureEligible(User $user, Event $event): void
    {
        if (!is_null($event->classic_level_min) && !is_null($user->classic_level)) {
            if ($user->classic_level < $event->classic_level_min) {
                abort(403, 'Ваш уровень в классике ниже требуемого для этого мероприятия.');
            }
        }

        if (!is_null($event->beach_level_min) && !is_null($user->beach_level)) {
            if ($user->beach_level < $event->beach_level_min) {
                abort(403, 'Ваш уровень в пляже ниже требуемого для этого мероприятия.');
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function missingPersonalData(User $user): array
    {
        $missing = [];

        if (empty($user->last_name) || empty($user->first_name)) {
            $missing[] = 'full_name';
        }

        if (empty($user->phone)) {
            $missing[] = 'phone';
        }

        if (empty($user->email)) {
            $missing[] = 'email';
        }

        return $missing;
    }
}
