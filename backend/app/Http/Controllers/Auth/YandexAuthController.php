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

    private function logWarn(string $msg, array $ctx = []): void
    {
        if (!$this->debug()) return;
        Log::warning('[YANDEX_OAUTH] ' . $msg, $ctx);
    }

    private function storeReturnTo(Request $request): void
    {
        $returnTo = (string) ($request->query('return') ?: url()->previous() ?: '');
        $returnTo = $this->sanitizeReturnTo($returnTo);
        $request->session()->put('oauth_return_to', $returnTo);
    }

    private function sanitizeReturnTo(string $url): string
    {
        $fallback = url('/events');

        $url = trim($url);
        if ($url === '') return $fallback;

        $appHost = parse_url(config('app.url'), PHP_URL_HOST);
        $urlHost = parse_url($url, PHP_URL_HOST);

        if ($urlHost && $appHost && strcasecmp($urlHost, $appHost) !== 0) {
            return $fallback;
        }

        if (str_contains($url, '/auth/') || str_contains($url, '/logout')) {
            return $fallback;
        }

        return $url;
    }

    private function popReturnTo(Request $request): string
    {
        return (string) $request->session()->pull('oauth_return_to', url('/events'));
    }

    public function redirect(Request $request)
    {
        $intent = Auth::check() ? 'link' : 'login';

        $this->storeReturnTo($request);

        $request->session()->put('oauth_provider', 'yandex');
        $request->session()->put('oauth_intent', $intent);

        // ВАЖНО: Yandex scope — пробелами
        $scopeStr = implode(' ', $this->scopes);

        $driver = Socialite::driver('yandex')
            ->scopes($this->scopes)
            ->with([
                'scope' => $scopeStr,
                'include_granted_scopes' => 'true',
            ]);

        // в debug можно посмотреть финальный URL
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
            }
        }

        return $driver->redirect();
    }

    public function callback(Request $request)
    {
        $returnTo = $this->popReturnTo($request);

        // Если Яндекс вернул ошибку
        if ($request->has('error')) {
            $this->logWarn('callback() error from yandex', [
                'error' => (string) $request->query('error'),
                'error_description' => (string) $request->query('error_description'),
                'query_keys' => array_keys($request->query()),
                'scopes_requested' => $this->scopes,
            ]);

            return redirect()->route('login')->with(
                'error',
                'Яндекс OAuth: ' . (string) $request->query('error') . ' — ' . (string) $request->query('error_description')
            );
        }

        try {
            // stateful
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

        // только безопасные метки в debug
        $this->logWarn('callback() user ok', [
            'id' => $yandexId,
            'has_email' => ($email !== ''),
            'has_avatar' => !empty($avatar),
            'raw_keys' => array_keys($raw),
            'has_birthday' => !empty($raw['birthday'] ?? null),
            'has_default_phone' => !empty($raw['default_phone']['number'] ?? null),
        ]);

        $intent = (string) $request->session()->pull('oauth_intent', Auth::check() ? 'link' : 'login');
        $request->session()->forget('oauth_provider');

        // -------------------------
        // LINK
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

            UserPhotoFromProviderService::seedFromProviderIfAllowed($current, $avatar, false);

            $request->session()->put('auth_provider', 'yandex');
            $request->session()->put('auth_provider_id', $yandexId);

            return redirect()->to($returnTo)->with('status', 'Яндекс привязан ✅');
        }

        // -------------------------
        // LOGIN
        // -------------------------
        $user = User::query()->where('yandex_id', $yandexId)->first();

        // (опционально) привязка по email
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

            if ($user->isFillable('name')) $user->name = $displayName;
            if ($user->isFillable('email')) $user->email = $safeEmail;
            if ($user->isFillable('password')) $user->password = Hash::make(Str::random(32));

            if ($user->isFillable('yandex_id')) $user->yandex_id = $yandexId;
            if ($user->isFillable('yandex_email') && $email !== '') $user->yandex_email = $email;

            $user->save();
        }

        // мягкое заполнение профиля данными Яндекса (ТОЛЬКО если пусто)
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
            $user->phone = $yaPhone; // E.164
            $changed = true;
        }

        if ($user->isFillable('gender') && empty($user->gender) && $yaSex) {
            $g = $yaSex === 'male' ? 'm' : ($yaSex === 'female' ? 'f' : null);
            if ($g) {
                $user->gender = $g;
                $changed = true;
            }
        }

        if ($changed) {
            $user->save();
        }

        UserPhotoFromProviderService::seedFromProviderIfAllowed($user, $avatar, $isNewUser);

        Auth::login($user, true);
        $request->session()->regenerate();

        $request->session()->put('auth_provider', 'yandex');
        $request->session()->put('auth_provider_id', $yandexId);

        return redirect()->to($returnTo);
    }
}
