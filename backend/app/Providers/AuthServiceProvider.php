<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        /**
         * Базовые роли
         */
        Gate::define('is-admin', fn ($user) => ($user->role ?? 'user') === 'admin');
        Gate::define('is-organizer', fn ($user) => ($user->role ?? 'user') === 'organizer');
        Gate::define('is-staff', fn ($user) => ($user->role ?? 'user') === 'staff');

        /**
         * Заявки на organizer — обрабатывает только admin
         */
        Gate::define('approve-organizer-request', fn ($user) => ($user->role ?? 'user') === 'admin');

        /**
         * Видимость "скрытых" полей профиля:
         * - сам пользователь
         * - admin
         * - organizer
         * - staff
         */
        Gate::define('view-sensitive-profile', function ($user, $targetUser) {
            if ((int)$user->id === (int)$targetUser->id) {
                return true;
            }

            return in_array($user->role ?? 'user', ['admin', 'organizer', 'staff'], true);
        });

        /**
         * Редактирование "скрытых" персональных полей профиля (ФИО/отчество/телефон/дата/город):
         * По твоему ТЗ: organizer/staff видят, но НЕ редактируют => только admin
         */
        Gate::define('edit-sensitive-profile', fn ($user) => ($user->role ?? 'user') === 'admin');

        /**
         * Редактирование уровней игрока после первичного выбора:
         * - admin: да
         * - organizer: да
         * - staff: нет (по твоему описанию)
         */
        Gate::define('edit-player-levels', fn ($user) => in_array($user->role ?? 'user', ['admin', 'organizer'], true));

        /**
         * Управление мероприятием:
         * - admin: любое
         * - organizer: только свои (events.organizer_id = user.id)
         * - staff: только мероприятия organizer-а, который его назначил (organizer_staff)
         */
        Gate::define('manage-event', function ($user, $event) {
            $role = $user->role ?? 'user';

            if ($role === 'admin') {
                return true;
            }

            if ($role === 'organizer') {
                return (int)$event->organizer_id === (int)$user->id;
            }

            if ($role === 'staff') {
                if (empty($event->organizer_id)) {
                    return false;
                }

                return DB::table('organizer_staff')
                    ->where('organizer_id', (int)$event->organizer_id)
                    ->where('staff_user_id', (int)$user->id)
                    ->exists();
            }

            return false;
        });

        /**
         * Полное удаление мероприятия:
         * - admin: да
         * - organizer: только свои
         * - staff: нет (может только "пометить", реализуем позже)
         */
        Gate::define('delete-event', function ($user, $event) {
            $role = $user->role ?? 'user';

            if ($role === 'admin') {
                return true;
            }

            if ($role === 'organizer') {
                return (int)$event->organizer_id === (int)$user->id;
            }

            return false;
        });

        /**
         * Назначение staff:
         * - admin может назначать кому угодно
         * - organizer может назначать staff себе (в связку organizer_staff)
         */
        Gate::define('assign-staff', fn ($user) => in_array($user->role ?? 'user', ['admin', 'organizer'], true));
    }
}
