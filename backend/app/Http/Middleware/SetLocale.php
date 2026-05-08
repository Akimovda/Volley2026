<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $available = (array) config('app.available_locales', ['ru', 'en']);
        $fallback  = (string) config('app.locale', 'ru');

        $locale = null;

        $user = $request->user();
        if ($user && !empty($user->locale) && in_array($user->locale, $available, true)) {
            $locale = $user->locale;
        }

        if ($locale === null) {
            $sessionLocale = $request->session()->get('locale');
            if (is_string($sessionLocale) && in_array($sessionLocale, $available, true)) {
                $locale = $sessionLocale;
            }
        }

        if ($locale === null) {
            $locale = $fallback;
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
