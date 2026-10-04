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

    /** Языки СНГ — для них дефолт RU, для остальных EN. */
    private const CIS_LANGUAGES = ['ru', 'uk', 'be', 'kk', 'ky', 'uz', 'tg', 'hy', 'az', 'tk', 'ka'];

    /**
     * Нет заголовка Accept-Language (боты, curl) — $fallback (RU), чтобы не менять выдачу для поисковиков.
     * Если среди принимаемых языков есть хоть один из СНГ (в т.ч. «en-US,en;q=0.9,ru;q=0.8» у русскоязычных
     * с английской ОС) — RU, иначе EN.
     */
    private static function detectFromRequest(Request $request, array $available, string $fallback): string
    {
        $languages = $request->getLanguages();
        if ($languages === []) {
            return $fallback;
        }

        foreach ($languages as $tag) {
            $primary = strtolower(substr(str_replace('_', '-', (string) $tag), 0, 2));
            if (in_array($primary, self::CIS_LANGUAGES, true)) {
                return in_array('ru', $available, true) ? 'ru' : $fallback;
            }
        }

        return in_array('en', $available, true) ? 'en' : $fallback;
    }
}
