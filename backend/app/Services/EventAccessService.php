<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventAccessService
{
    /**
     * Проверяет, может ли пользователь вообще создавать события.
     */
    public function ensureCanCreateEvents(User $user): void
    {
        $role = (string) ($user->role ?? 'user');

        if (!in_array($role, ['admin', 'organizer', 'staff'], true)) {
            abort(403);
        }
    }

    /**
     * Проверка: может ли пользователь создавать событие
     * от имени указанного organizer.
     */
    public function assertCreatorCanUseOrganizer(User $user, ?int $organizerId): void
    {
        $role = (string) ($user->role ?? 'user');

        /*
        |--------------------------------------------------------------------------
        | STAFF
        |--------------------------------------------------------------------------
        */
        if ($role === 'staff') {

            $resolvedOrganizerId = $this->resolveOrganizerIdForStaff($user);

            if ($resolvedOrganizerId <= 0) {
                throw ValidationException::withMessages([
                    'organizer_id' => [
                        'Staff не привязан к organizer — создание мероприятий запрещено.'
                    ]
                ]);
            }

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | ADMIN
        |--------------------------------------------------------------------------
        */
        if ($role === 'admin') {

            // админ может оставить organizer пустым
            if (!$organizerId || $organizerId <= 0) {
                return;
            }

            // админ может создать событие "как он сам"
            if ((int) $organizerId === (int) $user->id) {
                return;
            }

            $exists = User::whereKey($organizerId)
                ->where('role', 'organizer')
                ->exists();

            if (!$exists) {
                throw ValidationException::withMessages([
                    'organizer_id' => [
                        'Неверный organizer_id (можно выбрать organizer или оставить пустым — тогда будет админ).'
                    ]
                ]);
            }

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | ORGANIZER
        |--------------------------------------------------------------------------
        */
        if ($role === 'organizer') {

            if ($organizerId && (int) $organizerId !== (int) $user->id) {
                throw ValidationException::withMessages([
                    'organizer_id' => [
                        'Organizer может создавать мероприятия только от своего имени.'
                    ]
                ]);
            }

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | DEFAULT (user)
        |--------------------------------------------------------------------------
        */
        throw ValidationException::withMessages([
            'organizer_id' => [
                'Недостаточно прав для создания мероприятия.'
            ]
        ]);
    }

    /**
     * Получить organizer_id для staff пользователя.
     */
    public function resolveOrganizerIdForStaff(User $user): int
    {
        $row = DB::table('organizer_staff')
            ->where('staff_user_id', (int) $user->id)
            ->orderBy('id')
            ->first(['organizer_id']);

        return $row ? (int) $row->organizer_id : 0;
    }

    /**
     * Определяет organizer_id от имени которого пользователь создаёт событие.
     */
    public function resolveOrganizerIdForCreator(User $user): int
    {
        return match ((string) ($user->role ?? 'user')) {
            'organizer' => (int) $user->id,
            'staff' => $this->resolveOrganizerIdForStaff($user),
            default => 0, // admin
        };
    }
}