<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        //
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        // Роли
        Gate::define('is-admin', fn ($user) => ($user->role ?? 'user') === 'admin');
        Gate::define('is-organizer', fn ($user) => ($user->role ?? 'user') === 'organizer');
        Gate::define('is-staff', fn ($user) => ($user->role ?? 'user') === 'staff');

        // Заявки на organizer — только admin
        Gate::define('approve-organizer-request', fn ($user) => ($user->role ?? 'user') === 'admin');

        /**
         * Видимость "скрытых" полей профиля (patronymic/phone):
         * - владелец профиля
         * - admin / organizer / staff
         * ВАЖНО: гость (null) не видит.
         */
        Gate::define('view-sensitive-profile', function ($viewer, $targetUser) {
            if (!$viewer || !$targetUser) {
                return false;
            }

            if ((int) $viewer->id === (int) $targetUser->id) {
                return true;
            }

            return in_array($viewer->role ?? 'user', ['admin', 'organizer', 'staff'], true);
        });

        /**
         * Редактирование "зафиксированных" полей после первичного заполнения:
         * - только admin
         */
        Gate::define('edit-protected-profile-fields', fn ($user) => ($user->role ?? 'user') === 'admin');

        /**
         * Редактирование уровней после первичного выбора:
         * - admin, organizer
         */
        Gate::define('edit-player-levels', fn ($user) => in_array($user->role ?? 'user', ['admin', 'organizer'], true));

        /**
         * Управление мероприятием:
         * - admin: любое
         * - organizer: свои (events.organizer_id = user.id)
         * - staff: мероприятия organizer-а, который назначил staff (organizer_staff)
         */
        Gate::define('manage-event', function ($user, $event) {
            $role = $user->role ?? 'user';

            if ($role === 'admin') {
                return true;
            }

            if ($role === 'organizer') {
                return (int) $event->organizer_id === (int) $user->id;
            }

            if ($role === 'staff') {
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

        // Полное удаление мероприятия: admin, organizer(свои)
        Gate::define('delete-event', function ($user, $event) {
            $role = $user->role ?? 'user';

            if ($role === 'admin') {
                return true;
            }

            if ($role === 'organizer') {
                return (int) $event->organizer_id === (int) $user->id;
            }

            return false;
        });

        // Назначение staff: admin, organizer
        Gate::define('assign-staff', fn ($user) => in_array($user->role ?? 'user', ['admin', 'organizer'], true));
    }
}
