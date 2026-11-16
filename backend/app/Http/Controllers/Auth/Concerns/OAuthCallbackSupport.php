<?php

namespace App\Http\Controllers\Auth\Concerns;

use App\Models\User;
use App\Support\RaceSafeUserSave;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Общее для входов через провайдеров (VK, Google, Яндекс, Apple, Telegram, MAX):
 *  - адрес возврата (store/sanitize/pop), защита от open redirect;
 *  - защита от повторного callback (двойной тап, провайдер повторно возвращает пользователя);
 *  - создание пользователя, устойчивое к двойному запросу (RaceSafeUserSave) и к занятому email;
 *  - учёт исходов входа (success/error) для метрик в Kuma (команда auth:report-metrics).
 * Контроллер может переопределить любой метод (класс важнее трейта).
 */
trait OAuthCallbackSupport
{
    private function storeReturnTo(Request $request): void
    {
        $returnTo = (string) ($request->query('return') ?: url()->previous() ?: '');
        $request->session()->put('oauth_return_to', $this->sanitizeReturnTo($returnTo));
    }

    /** Только свой хост (или относительный путь) и не служебные страницы входа/выхода. */
    private function sanitizeReturnTo(string $url): string
    {
        $fallback = url('/events');
        $url = trim($url);
        if ($url === '') return $fallback;

        // относительный путь допустим, но не «//host» (протокол-относительный адрес чужого хоста)
        if ($url[0] === '/' && !str_starts_with($url, '//')) {
            $absolute = url($url);
        } else {
            $appHost = parse_url(config('app.url'), PHP_URL_HOST);
            $urlHost = parse_url($url, PHP_URL_HOST);
            $scheme  = strtolower((string) parse_url($url, PHP_URL_SCHEME));
            if (!$urlHost || !$appHost || strcasecmp($urlHost, $appHost) !== 0 || !in_array($scheme, ['http', 'https'], true)) {
                return $fallback;
            }
            $absolute = $url;
        }
        foreach (['/auth/', '/logout', '/login', '/register'] as $bad) {
            if (str_contains($absolute, $bad)) return $fallback;
        }

        return $absolute;
    }

    private function popReturnTo(Request $request): string
    {
        return (string) $request->session()->pull('oauth_return_to', url('/events'));
    }

    /**
     * Повторный callback: пользователь уже вошёл и ожидающей привязки нет — ничего не обрабатываем, ведём дальше
     * (иначе повтор шёл как «привязка» и мог показать ошибку или затереть сессию). null — обрабатывать как обычно.
     */
    private function duplicateCallbackRedirect(Request $request, bool $applies = true): ?RedirectResponse
    {
        if ($applies && Auth::check() && $request->session()->get('oauth_intent') !== 'link') {
            return redirect()->to($this->popReturnTo($request));
        }

        return null;
    }

    /**
     * Создаёт пользователя для входа через провайдера.
     * @param string $email         email от провайдера ('' — нет; тогда берём $fallbackEmail)
     * @param string $fallbackEmail запасной служебный адрес вида vk_123@vk.local; если занят — добавляется случайный суффикс
     * @param array  $attrs         дополнительные поля (first_name, telegram_username, …)
     * ->wasRecentlyCreated == false, если пользователя уже создал параллельный запрос.
     */
    private function createOAuthUser(string $providerColumn, string|int $providerId, string $name, string $email, string $fallbackEmail, array $attrs = []): User
    {
        $candidate = $email !== '' ? $email : $fallbackEmail;
        if (User::where('email', $candidate)->exists()) {
            $candidate = preg_replace('/@/', '_' . Str::random(6) . '@', $fallbackEmail, 1);
        }

        $user = new User();
        $user->email    = $candidate;
        $user->password = Hash::make(Str::random(32));
        $user->name     = $name;
        $user->{$providerColumn} = $providerId;
        foreach ($attrs as $key => $value) {
            $user->{$key} = $value;
        }

        return RaceSafeUserSave::save($user, $providerColumn, $providerId);
    }

    /** Учёт исхода входа (для метрик). Никогда не ломает сам вход. */
    private function recordAuth(string $provider, string $outcome, ?string $reason = null): void
    {
        try {
            DB::table('auth_login_events')->insert([
                'provider' => $provider, 'outcome' => $outcome,
                'reason' => $reason !== null ? mb_substr($reason, 0, 160) : null, 'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // метрики вторичны
        }
    }

    private function authSuccess(string $provider, bool $isNew = false): void
    {
        $this->recordAuth($provider, $isNew ? 'success_new' : 'success');
    }

    /** Ошибка входа с возвратом на заданный адрес (Telegram возвращает на страницу, откуда начинали). */
    private function authFailTo(string $provider, string $to, string $message): RedirectResponse
    {
        $this->recordAuth($provider, 'error', $message);

        return redirect()->to($to)->with('error', $message);
    }

    /** Ошибка входа: на страницу входа с сообщением + учёт для метрик. */
    private function authFail(string $provider, string $message): RedirectResponse
    {
        $this->recordAuth($provider, 'error', $message);

        return redirect()->route('login')->with('error', $message);
    }
}
