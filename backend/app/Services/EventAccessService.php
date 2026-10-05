<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventAccessService
{
    /**
     * Отказ не-организатору. Обычного пользователя (role=user) отправляем на форму
     * заявки в профиле с флагом для всплывающего пояснения; остальным — 403.
     */
    public static function denyNonOrganizer(?User $user): never
    {
        if ($user && (string) ($user->role ?? 'user') === 'user' && !request()->expectsJson()) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                redirect()->to(route('profile.show') . '#organizer-request')
                    ->with('organizer_request_notice', true)
            );
        }
        abort(403);
    }

    /**
     * Проверяет, может ли пользователь вообще создавать события.
     */
    public function ensureCanCreateEvents(User $user): void
    {
        $role = (string) ($user->role ?? 'user');

        if (!in_array($role, ['admin', 'organizer', 'staff'], true)) {
            self::denyNonOrganizer($user);
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

            // Staff создаёт мероприятия только от имени СВОЕГО организатора —
            // явно переданный чужой organizer_id не принимаем.
            if ($organizerId && (int) $organizerId !== $resolvedOrganizerId) {
                throw ValidationException::withMessages([
                    'organizer_id' => [
                        'Staff может создавать мероприятия только от имени своего организатора.'
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
                ->whereIn('role', ['organizer', 'admin'])
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

            if ($organizerId && !in_array((int) $organizerId, $this->creatableOrganizerIds($user), true)) {
                throw ValidationException::withMessages([
                    'organizer_id' => [
                        'Organizer может создавать мероприятия только от своего имени или от имени организатора, у которого он помощник.'
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

    /**
     * Доступ к управлению КОНКРЕТНЫМ мероприятием: владелец ИЛИ staff у
     * владельца (запись в organizer_staff) — независимо от текущей роли
     * пользователя. Повышение staff до organizer (собственные мероприятия)
     * НЕ отменяет ранее выданное staff-назначение на чужих мероприятиях —
     * см. баг "Staff теряет доступ к чужим мероприятиям, став организатором".
     */
    public function canManageEvent(User $user, int $eventOrganizerId): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($eventOrganizerId <= 0) {
            return false;
        }

        if ((int) $eventOrganizerId === (int) $user->id) {
            return true;
        }

        return DB::table('organizer_staff')
            ->where('organizer_id', $eventOrganizerId)
            ->where('staff_user_id', (int) $user->id)
            ->exists();
    }

    /**
     * organizer_id'ы, чьи мероприятия пользователь вправе администрировать:
     * свой собственный + все, у кого он числится staff (organizer_staff).
     * Не зависит от роли пользователя — см. canManageEvent().
     */
    public function manageableOrganizerIds(User $user): array
    {
        $staffOrgIds = DB::table('organizer_staff')
            ->where('staff_user_id', (int) $user->id)
            ->pluck('organizer_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_unique(array_merge([(int) $user->id], $staffOrgIds)));
    }

    /**
     * Организаторы, от чьего имени пользователь может создавать мероприятия:
     * он сам (если у него своя роль organizer/admin) + все, у кого он помощник.
     * Если пользователь только помощник (роль staff) — свой id не включается.
     */
    public function creatableOrganizerIds(User $user): array
    {
        $role = (string) ($user->role ?? 'user');
        $ids = DB::table('organizer_staff')
            ->where('staff_user_id', (int) $user->id)
            ->pluck('organizer_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (in_array($role, ['organizer', 'admin'], true)) {
            array_unshift($ids, (int) $user->id);
        }

        return array_values(array_unique($ids));
    }

    /**
     * Организаторы, чьими абонементами и купонами пользователь вправе управлять:
     * он сам + те, у кого он помощник с флагом «мастер» (can_manage_subs).
     */
    public function subsOrganizerIds(User $user): array
    {
        $ids = DB::table('organizer_staff')
            ->where('staff_user_id', (int) $user->id)
            ->where('can_manage_subs', true)
            ->pluck('organizer_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $ids[] = (int) $user->id;

        return array_values(array_unique($ids));
    }

    public function canManageSubsOf(User $user, int $organizerId): bool
    {
        return $user->isAdmin() || in_array($organizerId, $this->subsOrganizerIds($user), true);
    }

    /**
     * Помощник (роль staff) без флага «мастер» к абонементам и купонам не допускается.
     */
    public function ensureCanUseSubs(User $user): void
    {
        if ($user->isAdmin()) {
            return;
        }
        if ((string) ($user->role ?? 'user') === 'staff' && count($this->subsOrganizerIds($user)) < 2) {
            abort(403);
        }
    }

    /**
     * Организатор для нового шаблона: явно выбранный (если разрешён), иначе
     * у помощника-мастера — его организатор, у остальных — он сам.
     */
    public function resolveSubsOrganizerId(User $user, ?int $requested): int
    {
        $allowed = $this->subsOrganizerIds($user);

        if ($requested && in_array($requested, $allowed, true)) {
            return $requested;
        }

        if ((string) ($user->role ?? 'user') === 'staff') {
            foreach ($allowed as $id) {
                if ($id !== (int) $user->id) {
                    return $id;
                }
            }
        }

        return (int) $user->id;
    }
}
