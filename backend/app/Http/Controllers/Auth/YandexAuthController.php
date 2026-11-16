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

class YandexAuthController extends Controller
{
    use OAuthCallbackSupport;

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

        $request->session()->save();

        return Socialite::driver('yandex')
            ->scopes($this->scopes)
            ->with(['scope' => implode(' ', $this->scopes)])
            ->redirect();
    }

    public function callback(Request $request)
    {
        if ($dup = $this->duplicateCallbackRedirect($request)) {
            return $dup;
        }

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
            return $this->authFail('yandex', 'Не удалось войти через Яндекс. Попробуйте ещё раз.');
        }

        $yandexId = (string) $yaUser->getId();
        // is_avatar_empty=true — у человека нет фото, getAvatar() всё равно вернёт URL заглушки: не сохраняем её как аватар
        $avatar   = $yaUser->getAvatar();
        if (!empty(($yaUser->user ?? [])['is_avatar_empty'])) {
            $avatar = null;
        }
        $raw      = is_array($yaUser->user ?? null) ? $yaUser->user : [];

        $intent = (string) $request->session()->pull('oauth_intent', Auth::check() ? 'link' : 'login');
        $request->session()->forget('oauth_provider');

        /* LINK */
        if ($intent === 'link') {
            if (!Auth::check()) {
                return redirect()->route('login')->with('error', __('Сначала войдите, чтобы привязать Яндекс.'));
            }

            $current = $request->user();

            if (User::where('yandex_id', $yandexId)->where('id', '!=', $current->id)->exists()
                || AccountLink::isTaken('yandex', $yandexId, $current->id)) {
                return redirect('/user/profile')->with('error', __('Этот Яндекс уже привязан к другому аккаунту.'));
            }

            $current->yandex_id = $yandexId;
            $current->save();

            UserPhotoFromProviderService::seedFromProviderIfAllowed($current, $avatar, false);

            $request->session()->put('auth_provider', 'yandex');
            $request->session()->put('auth_provider_id', $yandexId);

            return redirect()->to($returnTo)->with('status', __('Яндекс привязан ✅'));
        }

        /* LOGIN */
        $user = User::where('yandex_id', $yandexId)->first();
        $isNewUser = false;

        if (!$user) {
            // Провайдер id мог остаться алиасом на другого пользователя после merge
            // (UserMergeService, primary уже владел своим yandex_id) — резолвим туда,
            // приоритет выше эвристики по телефону ниже.
            $user = AccountLink::resolveUser('yandex', $yandexId);
        }

        // Нормализованный телефон от Яндекса
        $yandexPhone = null;
        $rawPhone = $raw['default_phone']['number'] ?? null;
        if ($rawPhone) {
            $yandexPhone = '+' . preg_replace('/\D/', '', $rawPhone);
            if (strlen($yandexPhone) < 7) $yandexPhone = null;
        }

        if (!$user) {
            // Попытка найти существующего пользователя по телефону. Только аккаунт с ПОДТВЕРЖДЁННЫМ телефоном
            // (иначе кто угодно мог вписать в профиль чужой номер) и без другого Яндекса — чужую привязку не перехватываем.
            if ($yandexPhone) {
                $user = User::where('phone', $yandexPhone)
                    ->whereNotNull('phone_verified_at')
                    ->whereNull('yandex_id')
                    ->whereNull('merged_into_user_id')
                    ->first();
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

            $user = $this->createOAuthUser('yandex_id', $yandexId, trim(($yaUser->getName() ?: '') ?: 'Пользователь'), '', "yandex_{$yandexId}@yandex.local");
            $isNewUser = $user->wasRecentlyCreated; // false — пользователя уже создал параллельный запрос
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
        $this->authSuccess('yandex', $isNewUser);

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
