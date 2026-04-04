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
        if (!$this->debug()) {
            return;
        }
        Log::warning('[YANDEX_OAUTH] ' . $msg, $ctx);
    }

    /* -----------------------------------------------------------------
     | Return URL helpers
     |-----------------------------------------------------------------*/

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

        if ($url === '') {
            return $fallback;
        }

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

    /* -----------------------------------------------------------------
     | Redirect
     |-----------------------------------------------------------------*/

    public function redirect(Request $request)
    {
        // ЯВНО: кнопка "Привязать" всегда = link
        $intent = $request->boolean('link')
            ? 'link'
            : (Auth::check() ? 'link' : 'login');

        $this->storeReturnTo($request);

        $request->session()->put('oauth_provider', 'yandex');
        $request->session()->put('oauth_intent', $intent);

        $scopeStr = implode(' ', $this->scopes);

        $driver = Socialite::driver('yandex')
            ->scopes($this->scopes)
            ->with([
                'scope' => $scopeStr,
                'include_granted_scopes' => 'true',
            ]);

        if ($this->debug()) {
            try {
                $targetUrl = $driver->redirect()->getTargetUrl();
                $this->logWarn('redirect()', [
                    'intent' => $intent,
                    'target_url' => $targetUrl,
                ]);
                return redirect()->away($targetUrl);
            } catch (\Throwable $e) {
                $this->logWarn('redirect build failed', ['e' => $e->getMessage()]);
            }
        }

        return $driver->redirect();
    }

    /* -----------------------------------------------------------------
     | Callback
     |-----------------------------------------------------------------*/

    public function callback(Request $request)
    {
        $returnTo = $this->popReturnTo($request);

        if ($request->has('error')) {
            return redirect()->route('login')->with(
                'error',
                'Яндекс OAuth: ' .
                (string) $request->query('error') . ' — ' .
                (string) $request->query('error_description')
            );
        }

        try {
            $yaUser = Socialite::driver('yandex')->user();
        } catch (\Throwable $e) {
            $this->logWarn('Socialite user() failed', ['e' => $e->getMessage()]);
            return redirect()->route('login')
                ->with('error', 'Не удалось войти через Яндекс. Попробуйте ещё раз.');
        }

        $yandexId = (string) $yaUser->getId();
        $name     = (string) ($yaUser->getName() ?? '');
        $email    = (string) ($yaUser->getEmail() ?? '');
        $avatar   = $yaUser->getAvatar();
        $raw      = is_array($yaUser->user ?? null) ? $yaUser->user : [];

        $intent = (string) $request->session()->pull(
            'oauth_intent',
            Auth::check() ? 'link' : 'login'
        );

        $request->session()->forget('oauth_provider');

        /* ===============================================================
         | LINK (привязка к текущему аккаунту)
         |===============================================================*/
        if ($intent === 'link') {
            if (!Auth::check()) {
                return redirect()->route('login')
                    ->with('error', 'Сначала войдите, чтобы привязать Яндекс.');
            }

            /** @var User $current */
            $current = $request->user();

            $existsForOther = User::query()
                ->where('yandex_id', $yandexId)
                ->where('id', '!=', $current->id)
                ->exists();

            if ($existsForOther) {
                return redirect('/user/profile')
                    ->with('error', 'Этот Яндекс уже привязан к другому аккаунту.');
            }

            // 🔒 OAuth-поля — ТОЛЬКО прямое присваивание
            $current->yandex_id = $yandexId;

            if ($email !== '' && empty($current->yandex_email)) {
                $current->yandex_email = $email;
            }

            $current->save();

            UserPhotoFromProviderService::seedFromProviderIfAllowed(
                $current,
                $avatar,
                false
            );

            $request->session()->put('auth_provider', 'yandex');
            $request->session()->put('auth_provider_id', $yandexId);

            return redirect()->to($returnTo)
                ->with('status', 'Яндекс привязан ✅');
        }

        /* ===============================================================
         | LOGIN
         |===============================================================*/

        $user = User::query()
            ->where('yandex_id', $yandexId)
            ->first();

        // (опционально) привязка по email
        if (!$user && $email !== '') {
            $candidate = User::query()->where('email', $email)->first();

            if ($candidate) {
                $existsForOther = User::query()
                    ->where('yandex_id', $yandexId)
                    ->where('id', '!=', $candidate->id)
                    ->exists();

                if ($existsForOther) {
                    return redirect()->route('login')
                        ->with('error', 'Этот Яндекс уже привязан к другому аккаунту.');
                }

                if (empty($candidate->yandex_id)) {
                    $candidate->yandex_id = $yandexId;
                }

                if ($email !== '' && empty($candidate->yandex_email)) {
                    $candidate->yandex_email = $email;
                }

                $candidate->save();
                $user = $candidate;
            }
        }

        $isNewUser = false;

        if (!$user) {
            $isNewUser = true;

            $displayName = $name !== ''
                ? $name
                : "Yandex User #{$yandexId}";

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
            $user->name       = $displayName;
            $user->email      = $safeEmail;
            $user->password   = Hash::make(Str::random(32));
            $user->yandex_id  = $yandexId;

            if ($email !== '') {
                $user->yandex_email = $email;
            }

            $user->save();
        }

        /* ---------------------------------------------------------------
         | Мягкое заполнение профиля
         |---------------------------------------------------------------*/
        $data = [];

        if (empty($user->first_name) && !empty($raw['first_name'])) {
            $data['first_name'] = $raw['first_name'];
        }

        if (empty($user->last_name) && !empty($raw['last_name'])) {
            $data['last_name'] = $raw['last_name'];
        }

        if (empty($user->birth_date) && !empty($raw['birthday'])) {
            $data['birth_date'] = $raw['birthday']; // YYYY-MM-DD
        }

        if (
            empty($user->phone) &&
            !empty($raw['default_phone']['number'])
        ) {
            $data['phone'] = $raw['default_phone']['number'];
        }

        if (empty($user->gender) && !empty($raw['sex'])) {
            $g = $raw['sex'] === 'male'
                ? 'm'
                : ($raw['sex'] === 'female' ? 'f' : null);

            if ($g) {
                $data['gender'] = $g;
            }
        }

        if (!empty($data)) {
            $user->forceFill($data)->save();
        }

        UserPhotoFromProviderService::seedFromProviderIfAllowed(
            $user,
            $avatar,
            $isNewUser
        );

        Auth::login($user, true);
        $request->session()->regenerate();

        $request->session()->put('auth_provider', 'yandex');
        $request->session()->put('auth_provider_id', $yandexId);

        return redirect()->to($returnTo);
    }
}