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

        // Первый визит без сохранённого выбора: язык по Accept-Language
        // (нативные приложения — WebView, шлют язык устройства). Результат нигде не сохраняется,
        // поэтому явный выбор (профиль/сессия) всегда приоритетнее.
        if ($locale === null) {
            $locale = self::detectFromRequest($request, $available, $fallback);
        }

        app()->setLocale($locale);

        return $next($request);
    }

    /**
     * Нет заголовка Accept-Language (боты, curl) — $fallback (RU), чтобы не менять выдачу для поисковиков.
     * Иначе смотрим самый приоритетный язык клиента (в приложениях — системный язык устройства):
     * русский → RU, любой другой → EN.
     */
    private static function detectFromRequest(Request $request, array $available, string $fallback): string
    {
        $languages = $request->getLanguages();
        if ($languages === []) {
            return $fallback;
        }

        $primary = strtolower(substr(str_replace('_', '-', (string) $languages[0]), 0, 2));
        $locale  = $primary === 'ru' ? 'ru' : 'en';

        return in_array($locale, $available, true) ? $locale : $fallback;
    }
}
