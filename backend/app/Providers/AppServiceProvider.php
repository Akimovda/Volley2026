<?php

namespace App\Providers;

use App\Models\UserNotification;
use App\Models\EventRegistration;
use App\Support\AssetVersion;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use MoveMoveApp\VKID\VKIDExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;
use Illuminate\Pagination\Paginator;
use App\Observers\EventRegistrationObserver;
use App\Observers\UserObserver;
use App\Models\User;


class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

public function boot(): void
{
    EventRegistration::observe(EventRegistrationObserver::class);
        User::observe(UserObserver::class);

    Blade::directive('asset_v', function ($expression) {
        return "<?php echo \App\Support\AssetVersion::url($expression); ?>";
    });
    
View::composer('*', function ($view) {
    $notificationsUnread = 0;
    $unreadNotifications = collect(); // 👈 только непрочитанные

    if (auth()->check()) {
        $notificationsUnread = UserNotification::query()
            ->where('user_id', auth()->id())
            ->whereNull('read_at')
            ->count();
        
        // 👈 берем 5 последних непрочитанных
        $unreadNotifications = UserNotification::query()
            ->where('user_id', auth()->id())
            ->whereNull('read_at')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
    }

    $view->with('notificationsUnread', $notificationsUnread);
    $view->with('unreadNotifications', $unreadNotifications);
});

    Event::listen(
        SocialiteWasCalled::class,
        [VKIDExtendSocialite::class, 'handle']
    );
    
    // Добавьте эту строку
    Paginator::defaultView('pagination.custom');
}
}