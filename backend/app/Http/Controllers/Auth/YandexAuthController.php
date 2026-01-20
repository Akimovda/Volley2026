<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\UserPhotoFromProviderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class YandexAuthController extends Controller
{
    /**
     * Важно:
     * - Yandex ожидает scope через ПРОБЕЛ: "login:info login:email ..."
     * - Для продакшена логируем только безопасные поля и только когда app.debug=true
     */
    private array $scopes = [
        'login:info',
        'login:email',
        'login:birthday',
        'login:default_phone',
    ];

    private function debug(): bool
    {
        return (bool) config('app.debug');
    }

    private function logDebug(string $msg, array $ctx = []): void
    {
        if (!$this->debug()) return;
        Log::debug('[YANDEX_OAUTH] ' . $msg, $ctx);
    }

    private function logWarn(string $msg, array $ctx = []): void
    {
        if (!$this->debug()) return;
        Log::warning('[YANDEX_OAUTH] ' . $msg, $ctx);
    }

    public function redirect(Request $request)
    {
        $intent = Auth::check() ? 'link' : 'login';

        $request->session()->put('oauth_provider', 'yandex');
        $request->session()->put('oauth_intent', $intent);

        $scopeStr = implode(' ', $this->scopes); // <-- ключевой момент для Yandex

        // Социальный провайдер: редирект
        $driver = Socialite::driver('yandex')
            ->scopes($this->scopes)
            ->with([
                'scope' => $scopeStr,               // гарантируем пробелы
                'include_granted_scopes' => 'true', // удобно, если пользователь уже давал часть доступов
            ]);

        // Лог только в debug: показываем целевой URL, без персональных данных
        if ($this->debug()) {
            try {
                $targetUrl = $driver->redirect()->getTargetUrl();
                $this->logWarn('redirect()', [
                    'intent' => $intent,
                    'scopes_requested' => $this->scopes,
                    'scope_str' => $scopeStr,
                    'target_url' => $targetUrl,
                ]);
                return redirect()->away($targetUrl);
            } catch (\Throwable $e) {
                $this->logWarn('redirect() build url failed', ['e' => $e->getMessage()]);
                // fallthrough
            }
        }

        return $driver->redirect();
    }

    public function callback(Request $request)
    {
        // 1) Если Яндекс вернул ошибку — аккуратно покажем пользователю (и залогируем только в debug)
        if ($request->has('error')) {
            $this->logWarn('callback() error from yandex', [
                'error' => (string) $request->query('error'),
                'error_description' => (string) $request->query('error_description'),
                'raw_query_keys' => array_keys($request->query()),
                'scopes_requested' => $this->scopes,
            ]);

            return redirect()->route('login')->with(
                'error',
                'Яндекс OAuth: ' . (string) $request->query('error') . ' — ' . (string) $request->query('error_description')
            );
        }

        // 2) Получаем пользователя от Socialite
        // Для Yandex обычно state работает корректно -> stateless НЕ включаем.
        try {
            $yaUser = Socialite::driver('yandex')->user();
        } catch (\Throwable $e) {
            $this->logWarn('callback() Socialite::user() failed', [
                'e' => $e->getMessage(),
                'has_code' => $request->filled('code'),
                'query_keys' => array_keys($request->query()),
            ]);
            return redirect()->route('login')->with('error', 'Не удалось войти через Яндекс. Попробуйте ещё раз.');
        }

        $yandexId = (string) $yaUser->getId();
        $name     = (string) ($yaUser->getName() ?? '');
        $email    = (string) ($yaUser->getEmail() ?? '');
        $avatar   = $yaUser->getAvatar();

        $raw = $yaUser->user ?? [];
        $raw = is_array($raw) ? $raw : [];

        // Безопасный debug-лог (без полного raw!)
        $this->logWarn('callback() socialite user ok', [
            'id' => $yandexId,
            'has_email' => $email !== '',
            'has_avatar' => !empty($avatar),
            'raw_keys' => array_keys($raw),
            'has_birthday' => !empty($raw['birthday'] ?? null),
            'has_default_phone' => !empty($raw['default_phone']['number'] ?? null),
        ]);

        // Intent: link or login
        $intent = (string) $request->session()->pull('oauth_intent', Auth::check() ? 'link' : 'login');
        $request->session()->forget('oauth_provider');

        // -------------------------
        // LINK (привязка к текущему аккаунту)
        // -------------------------
        if ($intent === 'link') {
            if (!Auth::check()) {
                return redirect()->route('login')->with('error', 'Сначала войдите, чтобы привязать Яндекс.');
            }

            /** @var User $current */
            $current = $request->user();

            $existsForOther = User::query()
                ->where('yandex_id', $yandexId)
                ->where('id', '!=', $current->id)
                ->exists();

            if ($existsForOther) {
                return redirect('/user/profile')->with('error', 'Этот Яндекс уже привязан к другому аккаунту.');
            }

            if ($current->isFillable('yandex_id')) {
                $current->yandex_id = $yandexId;
            }
            if ($current->isFillable('yandex_email') && $email !== '' && empty($current->yandex_email)) {
                $current->yandex_email = $email;
            }

            $current->save();

            // Фото — только если разрешено в сервисе
            UserPhotoFromProviderService::seedFromProviderIfAllowed($current, $avatar, false);

            $request->session()->put('auth_provider', 'yandex');
            $request->session()->put('auth_provider_id', $yandexId);

            return redirect('/user/profile')->with('status', 'Яндекс привязан ✅');
        }

        // -------------------------
        // LOGIN (вход/регистрация)
        // -------------------------
        $user = User::query()->where('yandex_id', $yandexId)->first();

        // (опционально) если пришёл email — можно привязать существующего по email
        if (!$user && $email !== '') {
            $candidate = User::query()->where('email', $email)->first();
            if ($candidate) {
                $existsForOther = User::query()
                    ->where('yandex_id', $yandexId)
                    ->where('id', '!=', $candidate->id)
                    ->exists();

                if ($existsForOther) {
                    return redirect()->route('login')->with('error', 'Этот Яндекс уже привязан к другому аккаунту.');
                }

                if ($candidate->isFillable('yandex_id') && empty($candidate->yandex_id)) {
                    $candidate->yandex_id = $yandexId;
                }
                if ($candidate->isFillable('yandex_email') && $email !== '' && empty($candidate->yandex_email)) {
                    $candidate->yandex_email = $email;
                }
                $candidate->save();
                $user = $candidate;
            }
        }

        $isNewUser = false;

        if (!$user) {
            $isNewUser = true;

            $displayName = $name !== '' ? $name : "Yandex User #{$yandexId}";

            // users.email NOT NULL — нужен уникальный safe email
            $safeEmail = null;
            if ($email !== '' && !User::query()->where('email', $email)->exists()) {
                $safeEmail = $email;
            }
            if (!$safeEmail) {
                $safeEmail = "yandex_{$yandexId}@yandex.local";
                if (User::query()->where('email', $safeEmail)->exists()) {
                    $safeEmail = "yandex_{$yandexId}_" . Str::random(6) . "@yandex.local";
                }
            }

            $user = new User();

            if ($user->isFillable('name')) {
                $user->name = $displayName;
            }
            if ($user->isFillable('email')) {
                $user->email = $safeEmail;
            }
            if ($user->isFillable('password')) {
                $user->password = Hash::make(Str::random(32));
            }
            if ($user->isFillable('yandex_id')) {
                $user->yandex_id = $yandexId;
            }
            if ($user->isFillable('yandex_email') && $email !== '') {
                $user->yandex_email = $email;
            }

            $user->save();
        }

        // Опционально: “мягко” заполнить профиль данными Яндекса ТОЛЬКО если пусто
        // (и только если поля вообще существуют/fillable)
        if ($isNewUser) {
            $yaBirthday = $raw['birthday'] ?? null;
            $yaPhone    = $raw['default_phone']['number'] ?? null;
            $yaSex      = $raw['sex'] ?? null; // male/female
            $yaFirst    = $raw['first_name'] ?? null;
            $yaLast     = $raw['last_name'] ?? null;

            $changed = false;

            if ($user->isFillable('first_name') && empty($user->first_name) && $yaFirst) { $user->first_name = $yaFirst; $changed = true; }
            if ($user->isFillable('last_name')  && empty($user->last_name)  && $yaLast)  { $user->last_name  = $yaLast;  $changed = true; }

            if ($user->isFillable('birth_date') && empty($user->birth_date) && $yaBirthday) {
                $user->birth_date = $yaBirthday; // YYYY-MM-DD
                $changed = true;
            }

            if ($user->isFillable('phone') && empty($user->phone) && $yaPhone) {
                $user->phone = $yaPhone; // уже E.164
                $changed = true;
            }

            if ($user->isFillable('gender') && empty($user->gender) && $yaSex) {
                $user->gender = $yaSex === 'male' ? 'm' : ($yaSex === 'female' ? 'f' : null);
                $changed = $changed || ($user->gender !== null);
            }

            if ($changed) {
                $user->save();
            }
        }

        // Фото — только если разрешено в сервисе
        UserPhotoFromProviderService::seedFromProviderIfAllowed($user, $avatar, $isNewUser);

        Auth::login($user, true);
        $request->session()->regenerate();

        $request->session()->put('auth_provider', 'yandex');
        $request->session()->put('auth_provider_id', $yandexId);

        return redirect()->intended('/events');
    }
}
