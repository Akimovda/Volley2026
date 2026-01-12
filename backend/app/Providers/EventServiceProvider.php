<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;

// SocialiteProviders extenders
use SocialiteProviders\Yandex\YandexExtendSocialite;
// VKontakte extender нужен только если используете driver('vkontakte'):
// use SocialiteProviders\VKontakte\VKontakteExtendSocialite;

class EventServiceProvider extends ServiceProvider
{
    /**
     * SocialiteProviders подключаются через событие SocialiteWasCalled.
     *
     * Важно:
     * - Yandex (SocialiteProviders\Yandex) требует extender.
     * - VKID (driver 'vkid') в вашей конфигурации, как правило, НЕ через SocialiteProviders,
     *   поэтому здесь ничего добавлять не нужно.
     *
     * @var array<class-string, array<int, string>>
     */
    protected $listen = [
        SocialiteWasCalled::class => [
            YandexExtendSocialite::class.'@handle',

            // Если когда-нибудь будете использовать driver('vkontakte'), раскомментируйте:
            // VKontakteExtendSocialite::class.'@handle',
        ],
    ];

    public function boot(): void
    {
        // нам достаточно $listen
    }

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
