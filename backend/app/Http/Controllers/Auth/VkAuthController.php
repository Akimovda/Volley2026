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
use Laravel\Socialite\Two\InvalidStateException;

class VkAuthController extends Controller
{
    private function logWarn(string $msg, array $ctx = []): void
    {
        Log::warning('[VK_OAUTH] ' . $msg, $ctx);
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
        if (str_contains($url, '/auth/') || str_contains($url, '/logout') || str_contains($url, '/login') || str_contains($url, '/register')) return $fallback;

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
        $request->session()->put('oauth_provider', 'vk');
        $request->session()->put('oauth_intent', $intent);

        // TMA: запоминаем client_id для polling-механизма
        if ($request->filled('tma_client_id')) {
            $request->session()->put('oauth_tma_client_id', (string) $request->query('tma_client_id'));
        }

        // Socialite пишет state в сессию внутри redirect(); явно сохраняем после,
        // чтобы state гарантированно попал в БД до того как браузер уйдёт на VK.
        $response = Socialite::driver('vkid')->redirect();
        $request->session()->save();
        return $response;
    }

    public function callback(Request $request)
    {
        $returnTo = $this->popReturnTo($request);

        try {
            $vkUser = Socialite::driver('vkid')->user();
        } catch (InvalidStateException $e) {
            // Telegram WebView / iOS WKWebView теряют сессионную куку при cross-domain redirect.
            // Stateless-fallback: CSRF-токен пропускаем, но VK access_token всё равно валиден.
            try {
                $vkUser = Socialite::driver('vkid')->stateless()->user();
                $this->logWarn('stateless fallback used (session cookie lost)');
            } catch (\Throwable $e2) {
                $this->logWarn('callback failed (stateless also failed)', ['e' => $e2->getMessage()]);
                return redirect()->route('login')->with('error', 'VK: ошибка авторизации. Попробуйте ещё раз.');
            }
        } catch (\Throwable $e) {
            $this->logWarn('callback failed', ['e' => $e->getMessage()]);
            return redirect()->route('login')->with('error', 'VK: сессия авторизации истекла. Попробуйте ещё раз.');
        }

        $vkId  = (string) $vkUser->getId();
        $avatar = $vkUser->getAvatar();

        $intent = (string) $request->session()->pull('oauth_intent', Auth::check() ? 'link' : 'login');

        /* LINK */
        if ($intent === 'link') {
            if (!Auth::check()) {
                return redirect()->route('login')->with('error', 'Сначала войдите, чтобы привязать VK.');
            }

            $current = $request->user();

            if (User::where('vk_id', $vkId)->where('id', '!=', $current->id)->exists()) {
                return redirect('/user/profile')->with('error', 'Этот VK уже привязан к другому аккаунту.');
            }

            $current->vk_id = $vkId;
            $current->save();

            UserPhotoFromProviderService::seedFromProviderIfAllowed($current, $avatar, false);

            return redirect()->to($returnTo)->with('status', 'VK привязан ✅');
        }

        /* LOGIN */
        $user = User::where('vk_id', $vkId)->first();

        if (!$user) {
            $user = User::where('vk_notify_user_id', $vkId)->first();
            if ($user) {
                if (User::where('vk_id', $vkId)->where('id', '!=', $user->id)->exists()) {
                    return redirect()->route('login')->with('error', 'Этот VK уже привязан к другому аккаунту.');
                }
                $user->vk_id = $vkId;
                $user->save();
            }
        }

        $isNewUser = false;

        if (!$user) {
            $isNewUser = true;

            $safeEmail = "vk_{$vkId}@vk.local";
            if (User::where('email', $safeEmail)->exists()) {
                $safeEmail = "vk_{$vkId}_" . Str::random(6) . "@vk.local";
            }

            $user = new User();
            $user->email    = $safeEmail;
            $user->password = Hash::make(Str::random(32));
            $user->name     = trim(($vkUser->getName() ?: '') ?: 'Пользователь');
            $user->vk_id    = $vkId;
            $user->save();
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        UserPhotoFromProviderService::seedFromProviderIfAllowed($user, $avatar, $isNewUser);

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
