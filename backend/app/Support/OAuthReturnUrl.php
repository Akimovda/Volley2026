<?php

namespace App\Support;

use Illuminate\Http\Request;

final class OAuthReturnUrl
{
    private const SESSION_KEY = 'oauth_return_to';

    public static function remember(Request $request): void
    {
        // 1) explicit ?return_to=
        $returnTo = $request->query('return_to');

        // 2) если нет — берём предыдущий URL (страница, где была кнопка)
        if (!$returnTo) {
            $returnTo = url()->previous();
        }

        // фильтруем: только внутренние URL нашего домена
        $returnTo = self::sanitizeInternal($returnTo);

        // fallback
        $returnTo = $returnTo ?: route('home');

        $request->session()->put(self::SESSION_KEY, $returnTo);
    }

    public static function pull(Request $request, string $fallback): string
    {
        $fromSession = $request->session()->pull(self::SESSION_KEY);
        $fromSession = self::sanitizeInternal($fromSession);

        return $fromSession ?: $fallback;
    }

    private static function sanitizeInternal(?string $url): ?string
    {
        if (!$url) return null;

        // разрешаем только относительные пути или абсолютные нашего хоста
        $appHost = parse_url(config('app.url'), PHP_URL_HOST);
        $uHost   = parse_url($url, PHP_URL_HOST);

        // относительный "/events/123"
        if (!$uHost && str_starts_with($url, '/')) {
            return $url;
        }

        // абсолютный но наш домен
        if ($uHost && $appHost && strcasecmp($uHost, $appHost) === 0) {
            $path  = parse_url($url, PHP_URL_PATH) ?: '/';
            $query = parse_url($url, PHP_URL_QUERY);
            return $query ? ($path . '?' . $query) : $path;
        }

        return null;
    }
}
