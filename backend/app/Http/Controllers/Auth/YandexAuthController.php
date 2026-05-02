<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\UserPhotoFromProviderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class YandexAuthController extends Controller
{
    private array $scopes = ['login:info', 'login:email'];

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
        $request->session()->put('oauth_return_to', $this->sanitizeReturnTo($returnTo));
    }

    private function sanitizeReturnTo(string $url): string
    {
        $fallback = url('/events');
        $url = trim($url);
        if ($url === '') return $fallback;

        $appHost = parse_url(config('app.url'), PHP_URL_HOST);
        $urlHost = parse_url($url, PHP_URL_HOST);

        if ($urlHost && $appHost && strcasecmp($urlHost, $appHost) !== 0) return $fallback;
        if (str_contains($url, '/auth/') || str_contains($url, '/logout')) return $fallback;

        return $url;
    }

    private function popReturnTo(Request $request): string
    {
        return (string) $request->session()->pull('oauth_return_to', url('/events'));
    }

    public function redirect(Request $request)
    {
        $intent = $request->boolean('link') ? 'link' : (Auth::check() ? 'link' : 'login');
        $this->storeReturnTo($request);
        $request->session()->put('oauth_provider', 'yandex');
        $request->session()->put('oauth_intent', $intent);

        // TMA: запоминаем client_id для polling-механизма
        if ($request->filled('tma_client_id')) {
            $request->session()->put('oauth_tma_client_id', (string) $request->query('tma_client_id'));
        }

        return Socialite::driver('yandex')
            ->scopes($this->scopes)
            ->with(['scope' => implode(' ', $this->scopes)])
            ->redirect();
    }

    public function callback(Request $request)
    {
        $returnTo = $this->popReturnTo($request);

        if ($request->has('error')) {
            return redirect()->route('login')->with(
                'error',
                'Яндекс OAuth: ' . $request->query('error') . ' — ' . $request->query('error_description')
            );
        }

        try {
            $yaUser = Socialite::driver('yandex')->user();
        } catch (\Throwable $e) {
            $this->logWarn('Socialite user() failed', ['e' => $e->getMessage()]);
            return redirect()->route('login')->with('error', 'Не удалось войти через Яндекс. Попробуйте ещё раз.');
        }

        $yandexId = (string) $yaUser->getId();
        $avatar   = $yaUser->getAvatar();
        $raw      = is_array($yaUser->user ?? null) ? $yaUser->user : [];

        $intent = (string) $request->session()->pull('oauth_intent', Auth::check() ? 'link' : 'login');
        $request->session()->forget('oauth_provider');

        /* LINK */
        if ($intent === 'link') {
            if (!Auth::check()) {
                return redirect()->route('login')->with('error', 'Сначала войдите, чтобы привязать Яндекс.');
            }

            $current = $request->user();

            if (User::where('yandex_id', $yandexId)->where('id', '!=', $current->id)->exists()) {
                return redirect('/user/profile')->with('error', 'Этот Яндекс уже привязан к другому аккаунту.');
            }

            $current->yandex_id = $yandexId;
            $current->save();

            UserPhotoFromProviderService::seedFromProviderIfAllowed($current, $avatar, false);

            $request->session()->put('auth_provider', 'yandex');
            $request->session()->put('auth_provider_id', $yandexId);

            return redirect()->to($returnTo)->with('status', 'Яндекс привязан ✅');
        }

        /* LOGIN */
        $user = User::where('yandex_id', $yandexId)->first();
        $isNewUser = false;

        // Нормализованный телефон от Яндекса
        $yandexPhone = null;
        $rawPhone = $raw['default_phone']['number'] ?? null;
        if ($rawPhone) {
            $yandexPhone = '+' . preg_replace('/\D/', '', $rawPhone);
            if (strlen($yandexPhone) < 7) $yandexPhone = null;
        }

        if (!$user) {
            // Попытка найти существующего пользователя по телефону
            if ($yandexPhone) {
                $user = User::where('phone', $yandexPhone)->whereNull('merged_into_user_id')->first();
                if ($user) {
                    $user->yandex_id    = $yandexId;
                    $user->yandex_phone = $yandexPhone;
                    $user->save();
                    $this->logWarn('Linked yandex to existing user by phone', ['user_id' => $user->id, 'phone' => $yandexPhone]);
                }
            }
        }

        if (!$user) {
            $isNewUser = true;

            $safeEmail = "yandex_{$yandexId}@yandex.local";
            if (User::where('email', $safeEmail)->exists()) {
                $safeEmail = "yandex_{$yandexId}_" . Str::random(6) . "@yandex.local";
            }

            $user = new User();
            $user->email     = $safeEmail;
            $user->password  = Hash::make(Str::random(32));
            $user->name      = trim(($yaUser->getName() ?: '') ?: 'Пользователь');
            $user->yandex_id = $yandexId;
            if ($yandexPhone) {
                $user->phone        = $yandexPhone;
                $user->yandex_phone = $yandexPhone;
            }

            // Сохраняем только пол
            if (!empty($raw['sex'])) {
                $g = $raw['sex'] === 'male' ? 'm' : ($raw['sex'] === 'female' ? 'f' : null);
                if ($g) $user->gender = $g;
            }

            $user->save();
        } else {
            // Для существующих — обновляем пол и телефон если отсутствуют
            $changed = false;
            if (empty($user->gender) && !empty($raw['sex'])) {
                $g = $raw['sex'] === 'male' ? 'm' : ($raw['sex'] === 'female' ? 'f' : null);
                if ($g) { $user->gender = $g; $changed = true; }
            }
            if ($yandexPhone) {
                if (empty($user->yandex_phone)) { $user->yandex_phone = $yandexPhone; $changed = true; }
                if (empty($user->phone))         { $user->phone = $yandexPhone;        $changed = true; }
            }
            if ($changed) $user->save();
        }

        UserPhotoFromProviderService::seedFromProviderIfAllowed($user, $avatar, $isNewUser);

        Auth::login($user, true);
        $request->session()->regenerate();

        $request->session()->put('auth_provider', 'yandex');
        $request->session()->put('auth_provider_id', $yandexId);

        // TMA: сигнализируем polling-клиенту о завершении авторизации
        $tmaClientId = $request->session()->pull('oauth_tma_client_id');
        if ($tmaClientId) {
            $redirectUrl = $isNewUser ? route('profile.complete') : $returnTo;
            $token = Str::random(40);
            Cache::put("tma_auth_token_{$token}", ['user_id' => $user->id, 'redirect' => $redirectUrl], now()->addMinutes(5));
            Cache::put("tma_pending_{$tmaClientId}", ['token' => $token], now()->addMinutes(5));
            return view('auth.tma-oauth-done');
        }

        if ($isNewUser) {
            return redirect()->route('profile.complete')->with('welcome', true);
        }

        return redirect()->to($returnTo);
    }
}
