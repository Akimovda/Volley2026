<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Arr;
use Carbon\Carbon;

final class ProfileUpdateGuard
{
    // =========================================================
    // ROLE DEFINITIONS — SINGLE SOURCE OF TRUTH
    // =========================================================

public static function isAdmin(User $user): bool
{
    return in_array(
        strtolower((string) $user->role),
        ['admin', 'superadmin'],
        true
    );
}

public static function isOrganizer(User $user): bool
{
    return strtolower((string) $user->role) === 'organizer';
}

public static function isStaff(User $user): bool
{
    return strtolower((string) $user->role) === 'staff';
}

public static function isUser(User $user): bool
{
    return !self::isAdmin($user)
        && !self::isOrganizer($user)
        && !self::isStaff($user);
}

    // =========================================================
    // MAIN CHECK
    // =========================================================

    public static function check(User $actor, User $target, array $data): GuardResult
    {
        if (self::isAdmin($actor)) {
            return self::adminEdit($target, $data);
        }

        if (self::isOrganizer($actor) && $actor->id !== $target->id) {
            return self::organizerEdit($target, $data);
        }

        if ($actor->id === $target->id) {
            return self::selfEdit($target, $data);
        }

        return GuardResult::deny('Недостаточно прав для редактирования профиля.');
    }

    // =========================================================
    // ADMIN
    // =========================================================

    protected static function adminEdit(User $target, array $data): GuardResult
    {
        return self::validateAge($target, $data);
    }

    // =========================================================
    // ORGANIZER → OTHER USER
    // =========================================================

    protected static function organizerEdit(User $target, array $data): GuardResult
    {
        $allowedFields = [
            'birth_date',
            'classic_level',
            'beach_level',
            'classic_primary_position',
            'classic_extra_positions',
            'beach_mode',
        ];

        $filtered = Arr::only($data, $allowedFields);

        return self::validateAge($target, $filtered);
    }

    // =========================================================
    // USER → SELF
    // =========================================================

    protected static function selfEdit(User $target, array $data): GuardResult
    {
        // Если анкета ещё не была заполнена пользователем — все поля открыты для редактирования
        if (is_null($target->profile_completed_at)) {
            return self::validateAge($target, $data);
        }

        $protectedOnce = [
            'first_name',
            'last_name',
            'patronymic',
            'phone',
            'city_id',
        ];

        foreach ($protectedOnce as $field) {
            if (!empty($target->$field)) {
                unset($data[$field]);
            }
        }

        return self::validateAge($target, $data);
    }

    // =========================================================
    // AGE VALIDATION
    // =========================================================

    protected static function validateAge(User $target, array $data): GuardResult
    {
        $birth = $data['birth_date'] ?? $target->birth_date ?? null;

        if (!$birth) {
            return GuardResult::allow($data);
        }

        $age = Carbon::parse($birth)->age;

        if ($age < 18) {
            $allowedLevels = [1, 2, 4];

            foreach (['classic_level', 'beach_level'] as $field) {
                if (
                    isset($data[$field]) &&
                    !in_array((int) $data[$field], $allowedLevels, true)
                ) {
                    return GuardResult::deny(
                        'Недоступный уровень для игрока младше 18 лет.'
                    );
                }
            }
        }

        return GuardResult::allow($data);
    }

    // =========================================================
    // VIEW MODE FOR BLADE
    // =========================================================

    public static function viewMode(User $actor, User $target): ?string
    {
        if (self::isAdmin($actor)) {
            return $actor->id === $target->id
                ? 'admin_self'
                : 'admin_other';
        }

        if (self::isOrganizer($actor)) {
            return $actor->id === $target->id
                ? 'self'
                : 'organizer_other';
				}

        if ($actor->id === $target->id) {
            return 'self';
        }

        return null;
    }
}