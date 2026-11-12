<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AccountLink;
use App\Models\User;
use App\Support\UserPhotoFromProviderService;
use App\Http\Controllers\Auth\Concerns\OAuthCallbackSupport;
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
    use OAuthCallbackSupport;

    private function logWarn(string $msg, array $ctx = []): void
    {
        Log::warning('[VK_OAUTH] ' . $msg, $ctx);
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

        // Запасное хранилище state (10 мин): WebView/системный браузер иногда теряют сессионную куку при возврате с VK.
        // Раньше в этом случае проверка state просто отключалась (stateless) — теперь пропускаем только наш собственный,
        // выданный нами и ещё не использованный state.
        parse_str((string) parse_url($response->getTargetUrl(), PHP_URL_QUERY), $q);
        if (!empty($q['state'])) {
            Cache::put('vk_oauth_state_' . $q['state'], true, now()->addMinutes(10));
        }

        return $response;
    }

    public function callback(Request $request)
    {
        if ($dup = $this->duplicateCallbackRedirect($request)) {
            return $dup;
        }

        $returnTo = $this->popReturnTo($request);

        // state одноразовый: забираем из запасного хранилища при первом же callback
        $stateParam = (string) $request->query('state', '');
        $stateIsOurs = $stateParam !== '' && (bool) Cache::pull('vk_oauth_state_' . $stateParam);

        try {
            $vkUser = Socialite::driver('vkid')->user();
        } catch (InvalidStateException $e) {
            // Telegram WebView / iOS WKWebView теряют сессионную куку при cross-domain redirect.
            // Без куки допускаем только state, выданный нами (см. redirect()), иначе — отказ (защита от подмены входа).
            if (!$stateIsOurs) {
                $this->logWarn('callback rejected: invalid state and not in cache');
                return $this->authFail('vk', 'VK: сессия авторизации истекла. Попробуйте ещё раз.');
            }
            try {
                $vkUser = Socialite::driver('vkid')->stateless()->user();
                $this->logWarn('stateless fallback used (session cookie lost)');
            } catch (\Throwable $e2) {
                $this->logWarn('callback failed (stateless also failed)', ['e' => $e2->getMessage()]);
                return $this->authFail('vk', 'VK: ошибка авторизации. Попробуйте ещё раз.');
            }
        } catch (\Throwable $e) {
            $this->logWarn('callback failed', [
                'e'     => $e->getMessage(),
                'class' => get_class($e),
                'file'  => $e->getFile() . ':' . $e->getLine(),
                'trace' => array_slice(
                    array_map(fn($f) => ($f['class'] ?? '') . ($f['type'] ?? '') . ($f['function'] ?? '') . ' ' . ($f['file'] ?? '') . ':' . ($f['line'] ?? ''), $e->getTrace()),
                    0, 8
                ),
            ]);
            return $this->authFail('vk', 'VK: сессия авторизации истекла. Попробуйте ещё раз.');
        }

        $vkId  = (string) $vkUser->getId();
        $avatar = $vkUser->getAvatar();

        $intent = (string) $request->session()->pull('oauth_intent', Auth::check() ? 'link' : 'login');

        /* LINK */
        if ($intent === 'link') {
            if (!Auth::check()) {
                return redirect()->route('login')->with('error', __('Сначала войдите, чтобы привязать VK.'));
            }

            $current = $request->user();

            if (User::where('vk_id', $vkId)->where('id', '!=', $current->id)->exists()
                || AccountLink::isTaken('vk', $vkId, $current->id)) {
                return redirect('/user/profile')->with('error', __('Этот VK уже привязан к другому аккаунту.'));
            }

            $current->vk_id = $vkId;
            $current->save();

            UserPhotoFromProviderService::seedFromProviderIfAllowed($current, $avatar, false);

            return redirect()->to($returnTo)->with('status', __('VK привязан ✅'));
        }

        /* LOGIN */
        $user = User::where('vk_id', $vkId)->first();

        if (!$user) {
            $user = User::where('vk_notify_user_id', $vkId)->first();
            if ($user) {
                if (User::where('vk_id', $vkId)->where('id', '!=', $user->id)->exists()) {
                    return redirect()->route('login')->with('error', __('Этот VK уже привязан к другому аккаунту.'));
                }
                $user->vk_id = $vkId;
                $user->save();
            }
        }

        if (!$user) {
            // Провайдер id мог остаться алиасом на другого пользователя после merge
            // (UserMergeService, primary уже владел своим vk_id) — резолвим туда, не создаём дубль.
            $user = AccountLink::resolveUser('vk', $vkId);
        }

        $isNewUser = false;

        if (!$user) {
            $isNewUser = true;

            $user = $this->createOAuthUser('vk_id', $vkId, trim(($vkUser->getName() ?: '') ?: 'Пользователь'), '', "vk_{$vkId}@vk.local");
            $isNewUser = $user->wasRecentlyCreated; // false — пользователя уже создал параллельный запрос
        }

        Auth::login($user, true);
        $request->session()->regenerate();
        $request->session()->put('auth_provider', 'vk');
        $request->session()->put('auth_provider_id', $vkId);
        $this->authSuccess('vk', $isNewUser);

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
