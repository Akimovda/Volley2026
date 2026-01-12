<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;

// SocialiteProviders extenders
use SocialiteProviders\Yandex\YandexExtendSocialite;
// Нужен ТОЛЬКО если реально вызываешь Socialite::driver('vkontakte')
// use SocialiteProviders\VKontakte\VKontakteExtendSocialite;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * Важно: SocialiteProviders подключаются через событие SocialiteWasCalled.
     *
     * @var array<class-string, array<int, string>>
     */
    protected $listen = [
        SocialiteWasCalled::class => [
            // Добавляет driver('yandex')
            YandexExtendSocialite::class.'@handle',

            // Если когда-нибудь будешь использовать driver('vkontakte'), раскомментируй:
            // VKontakteExtendSocialite::class.'@handle',
        ],
    ];

    public function boot(): void
    {
        // Пусто — нам достаточно $listen
    }

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
