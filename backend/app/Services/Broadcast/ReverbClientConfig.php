<?php

declare(strict_types=1);

namespace App\Services\Broadcast;

/**
 * Публичные параметры подключения клиента к Reverb (через nginx `/app` на 80/443).
 * Нужны нативному приложению, чтобы не зашивать ключ в сборку (ключ ротируется на сервере).
 * Секрет сюда не попадает никогда — только публичный app key.
 */
class ReverbClientConfig
{
    /** @return array{key:string,host:string,port:int,scheme:string} */
    public static function get(): array
    {
        $url    = (string) config('app.url');
        $scheme = parse_url($url, PHP_URL_SCHEME) === 'http' ? 'http' : 'https';

        return [
            'key'    => (string) config('reverb.apps.apps.0.key'),
            'host'   => (string) (parse_url($url, PHP_URL_HOST) ?: ''),
            'port'   => $scheme === 'https' ? 443 : 80,
            'scheme' => $scheme,
        ];
    }
}
