<?php

namespace App\Providers;

use App\Models\UserNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use MoveMoveApp\VKID\VKIDExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;
use Illuminate\Pagination\Paginator;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

public function boot(): void
{
    View::composer('*', function ($view) {
        $notificationsUnread = 0;

        if (auth()->check()) {
            $notificationsUnread = UserNotification::query()
                ->where('user_id', auth()->id())
                ->whereNull('read_at')
                ->count();
        }

        $view->with('notificationsUnread', $notificationsUnread);
    });

    Event::listen(
        SocialiteWasCalled::class,
        [VKIDExtendSocialite::class, 'handle']
    );
    
    // Добавьте эту строку
    Paginator::defaultView('pagination.custom');
}
}