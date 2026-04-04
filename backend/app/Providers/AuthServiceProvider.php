<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\UserPolicy;
use App\Services\ProfileUpdateGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        User::class => UserPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        // --------------------------------------------------
        // БАЗОВЫЕ РОЛИ (ЕДИНАЯ ТОЧКА)
        // --------------------------------------------------

        Gate::define('is-admin', fn (?User $u) =>
            $u && $u->isAdmin()
        );

        Gate::define('is-organizer', fn (?User $u) =>
            $u && $u->isOrganizer()
        );

        Gate::define('is-staff', fn (?User $u) =>
            $u && method_exists($u, 'isStaff') && $u->isStaff()
        );

        // --------------------------------------------------
        // ЗАЯВКИ / УПРАВЛЕНИЕ РОЛЯМИ
        // --------------------------------------------------

        Gate::define('approve-organizer-request', fn (?User $u) =>
            $u && $u->isAdmin()
        );

        // --------------------------------------------------
        // ПРОФИЛЬ / ПЕРСОНАЛЬНЫЕ ДАННЫЕ
        // --------------------------------------------------

        /**
         * Просмотр чувствительных полей (phone, patronymic)
         */
        Gate::define('view-sensitive-profile', function (?User $viewer, User $target) {
            if (!$viewer) {
                return false;
            }

            if ($viewer->id === $target->id) {
                return true;
            }

            return $viewer->isAdmin()
                || $viewer->isOrganizer()
                || (method_exists($viewer, 'isStaff') && $viewer->isStaff());
        });

        /**
         * Редактирование "зафиксированных" полей
         * (имя, телефон после первого сохранения)
         */
        Gate::define('edit-protected-profile-fields', fn (?User $u) =>
            $u && $u->isAdmin()
        );

        /**
         * Редактирование уровней игрока
         */
        Gate::define('edit-player-levels', fn (?User $u) =>
            $u && ($u->isAdmin() || $u->isOrganizer())
        );

        /**
         * Редактирование профиля ДРУГОГО пользователя
         * (тонкий прокси к ProfileUpdateGuard)
         */
        Gate::define('edit-user-profile-extra', function (?User $actor, User $target) {
            if (!$actor) {
                return false;
            }

            return ProfileUpdateGuard::viewMode($actor, $target) !== null
                && $actor->id !== $target->id;
        });

        // --------------------------------------------------
        // МЕРОПРИЯТИЯ
        // --------------------------------------------------

        Gate::define('manage-event', function (User $user, $event) {
            if ($user->isAdmin()) {
                return true;
            }

            if ($user->isOrganizer()) {
                return (int) $event->organizer_id === (int) $user->id;
            }

            if (method_exists($user, 'isStaff') && $user->isStaff()) {
                if (empty($event->organizer_id)) {
                    return false;
                }

                return DB::table('organizer_staff')
                    ->where('organizer_id', (int) $event->organizer_id)
                    ->where('staff_user_id', (int) $user->id)
                    ->exists();
            }

            return false;
        });

        Gate::define('delete-event', function (User $user, $event) {
            if ($user->isAdmin()) {
                return true;
            }

            if ($user->isOrganizer()) {
                return (int) $event->organizer_id === (int) $user->id;
            }

            return false;
        });

        Gate::define('assign-staff', fn (?User $u) =>
            $u && ($u->isAdmin() || $u->isOrganizer())
        );
    }
}