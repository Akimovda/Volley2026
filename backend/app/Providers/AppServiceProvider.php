<?php

namespace App\Providers;

use App\Models\UserNotification;
use App\Models\EventRegistration;
use App\Services\GeoIpService;
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
        // Ведущий матча по токен-ссылке (routes/broadcast.php). Именованные лимитеры: безымянный throttle:N,M делит счётчик по IP
        // со всеми такими же, и трафик ввода душил бы оверлей (120/мин) на той же сети. Здесь, а не в routes: при route:cache файл роутов не исполняется.
        \Illuminate\Support\Facades\RateLimiter::for('scorer-ip', fn ($request) => \Illuminate\Cache\RateLimiting\Limit::perMinute(900)->by('scorer-ip:' . $request->ip()));
        \Illuminate\Support\Facades\RateLimiter::for('scorer', fn ($request) => \Illuminate\Cache\RateLimiting\Limit::perMinute(240)->by('scorer:' . (string) $request->route('token')));
        \Illuminate\Support\Facades\RateLimiter::for('overlay-page', fn ($request) => \Illuminate\Cache\RateLimiting\Limit::perMinute(30)->by('overlay-page:' . (string) $request->route('token') . ':' . $request->ip()));
        \Illuminate\Support\Facades\RateLimiter::for('overlay-state', fn ($request) => \Illuminate\Cache\RateLimiting\Limit::perMinute(300)->by('overlay-state:' . (string) $request->route('token') . ':' . $request->ip()));

    EventRegistration::observe(EventRegistrationObserver::class);
        User::observe(UserObserver::class);

    Blade::directive('asset_v', function ($expression) {
        return "<?php echo \App\Support\AssetVersion::url($expression); ?>";
    });
    
View::composer('*', function ($view) {
    static $isRussianIp = null;
    if ($isRussianIp === null) {
        $ip = request()->ip() ?? '';
        $isRussianIp = $ip ? app(GeoIpService::class)->isRussia($ip) : false;
    }
    $view->with('isRussianIp', $isRussianIp);

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