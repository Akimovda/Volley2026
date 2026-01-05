<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // Здесь можно регистрировать policy моделей при необходимости
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        /**
         * Кто может менять уровень игрока после первичного выбора
         *
         * - admin
         * - organizer
         * - assistant
         */
        Gate::define('edit-player-levels', function ($user) {
            return in_array(
                $user->role ?? 'user',
                ['admin', 'organizer', 'assistant'],
                true
            );
        });
    }
}
