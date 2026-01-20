<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\UserPhotoFromProviderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class VkAuthController extends Controller
{
    /**
     * Redirect to VKID.
     * - сохраняем intent в сессию
     * - строго stateful (без stateless тут)
     */
    public function redirect(Request $request)
    {
        $intent = Auth::check() ? 'link' : 'login';

        $request->session()->put('oauth_provider', 'vk');
        $request->session()->put('oauth_intent', $intent);

        return Socialite::driver('vkid')
            ->scopes(['email'])
            ->redirect();
    }

    /**
     * Callback от VKID.
     * ВАЖНО: без fallback stateless — если state потерялся, возвращаем юзера на login с понятной ошибкой.
     */
    public function callback(Request $request)
    {
        try {
            $vkUser = Socialite::driver('vkid')->user();
        } catch (\Throwable $e) {
            Log::warning('VKID callback failed (state/session lost?)', [
                'error' => $e->getMessage(),
            ]);

            // чистим intent/provider, чтобы не залипало
            $request->session()->forget('oauth_intent');
            $request->session()->forget('oauth_provider');

            return redirect()->route('login')
                ->with('error', 'VK: сессия авторизации истекла/потерялась. Нажми “Войти через VK” ещё раз.');
        }

        $vkId        = (string) $vkUser->getId();
        $name        = (string) ($vkUser->getName() ?? '');
        $avatar      = $vkUser->getAvatar();
        $accessToken = (string) ($vkUser->token ?? '');

        // email: иногда лежит не в getEmail(), а в accessTokenResponseBody['email']
        $vkEmail = (string) ($vkUser->getEmail() ?? '');
        $body = $vkUser->accessTokenResponseBody ?? null;
        if (empty($vkEmail) && is_array($body)) {
            $vkEmail = (string) ($body['email'] ?? '');
        }

        // Телефон: отдельный запрос к VK API по access_token
        $vkPhone = $this->fetchVkPhone($vkId, $accessToken);
        if (!empty($vkPhone) && !$this->isValidPhone($vkPhone)) {
            $vkPhone = null;
        }

        // intent из сессии (без query param)
        $intent = (string) $request->session()->pull('oauth_intent', Auth::check() ? 'link' : 'login');
        $request->session()->forget('oauth_provider');

        // -------------------------
        // LINK (привязка)
        // -------------------------
        if ($intent === 'link') {
            if (!Auth::check()) {
                return redirect()->route('login')->with('error', 'Сначала войдите, чтобы привязать VK.');
            }

            /** @var User $current */
            $current = $request->user();

            // anti-takeover: vk_id не может быть у другого user
            $existsForOther = User::query()
                ->where('vk_id', $vkId)
                ->where('id', '!=', $current->id)
                ->exists();

            if ($existsForOther) {
                return redirect('/user/profile')
                    ->with('error', 'Этот VK уже привязан к другому аккаунту.');
            }

            if ($current->isFillable('vk_id')) {
                $current->vk_id = $vkId;
            }

            if ($current->isFillable('vk_email') && !empty($vkEmail) && empty($current->vk_email)) {
                $current->vk_email = $vkEmail;
            }

            if (!empty($vkPhone)) {
                if ($current->isFillable('phone') && empty($current->phone)) {
                    $current->phone = $vkPhone;
                } elseif ($current->isFillable('vk_phone') && empty($current->vk_phone)) {
                    $current->vk_phone = $vkPhone;
                }
            }

            $current->save();

            // avatar -> только если галерея пустая (и не новый пользователь)
            UserPhotoFromProviderService::seedFromProviderIfAllowed($current, $avatar, false);

            $request->session()->put('auth_provider', 'vk');
            $request->session()->put('auth_provider_id', $vkId);

            return redirect('/user/profile')->with('status', 'VK привязан ✅');
        }

        // -------------------------
        // LOGIN
        // -------------------------
        $user = User::query()->where('vk_id', $vkId)->first();

        // (опционально) если email пришёл и есть совпадение — можно привязать к существующему
        if (!$user && !empty($vkEmail)) {
            $candidate = User::query()->where('email', $vkEmail)->first();
            if ($candidate) {
                $existsForOther = User::query()
                    ->where('vk_id', $vkId)
                    ->where('id', '!=', $candidate->id)
                    ->exists();

                if ($existsForOther) {
                    return redirect()->route('login')
                        ->with('error', 'Этот VK уже привязан к другому аккаунту.');
                }

                if ($candidate->isFillable('vk_id') && empty($candidate->vk_id)) {
                    $candidate->vk_id = $vkId;
                }

                if ($candidate->isFillable('vk_email') && !empty($vkEmail) && empty($candidate->vk_email)) {
                    $candidate->vk_email = $vkEmail;
                }

                if (!empty($vkPhone)) {
                    if ($candidate->isFillable('phone') && empty($candidate->phone)) {
                        $candidate->phone = $vkPhone;
                    } elseif ($candidate->isFillable('vk_phone') && empty($candidate->vk_phone)) {
                        $candidate->vk_phone = $vkPhone;
                    }
                }

                $candidate->save();
                $user = $candidate;
            }
        }

        $isNewUser = false;

        if (!$user) {
            $displayName = $name ?: "VK User #{$vkId}";

            // users.email NOT NULL -> безопасный surrogate email
            $safeEmail = null;

            if (!empty($vkEmail) && !User::query()->where('email', $vkEmail)->exists()) {
                $safeEmail = $vkEmail;
            }

            if (empty($safeEmail)) {
                $safeEmail = "vk_{$vkId}@vk.local";
                if (User::query()->where('email', $safeEmail)->exists()) {
                    $safeEmail = "vk_{$vkId}_" . Str::random(6) . "@vk.local";
                }
            }

            $user = new User();
            $isNewUser = true;

            if ($user->isFillable('name')) {
                $user->name = $displayName;
            }

            if ($user->isFillable('email')) {
                $user->email = $safeEmail;
            }

            if ($user->isFillable('password')) {
                $user->password = Hash::make(Str::random(32));
            }

            if ($user->isFillable('vk_id')) {
                $user->vk_id = $vkId;
            }

            if ($user->isFillable('vk_email') && !empty($vkEmail)) {
                $user->vk_email = $vkEmail;
            }

            if (!empty($vkPhone)) {
                if ($user->isFillable('phone')) {
                    $user->phone = $vkPhone;
                } elseif ($user->isFillable('vk_phone')) {
                    $user->vk_phone = $vkPhone;
                }
            }

            $user->save();
        } else {
            // при логине: можно добить телефон, если пусто
            if (!empty($vkPhone)) {
                $changed = false;

                if ($user->isFillable('phone') && empty($user->phone)) {
                    $user->phone = $vkPhone;
                    $changed = true;
                } elseif ($user->isFillable('vk_phone') && empty($user->vk_phone)) {
                    $user->vk_phone = $vkPhone;
                    $changed = true;
                }

                if ($changed) {
                    $user->save();
                }
            }
        }

        // avatar -> при регистрации всегда можно, при логине только если пусто (сервис сам решит)
        UserPhotoFromProviderService::seedFromProviderIfAllowed($user, $avatar, $isNewUser);

        Auth::login($user, true);
        $request->session()->regenerate();

        $request->session()->put('auth_provider', 'vk');
        $request->session()->put('auth_provider_id', $vkId);

        return redirect()->intended('/events');
    }

    /**
     * Добираем телефон после callback через VK API.
     * Важно: не логируем access_token и не логируем телефон/PII.
     */
    private function fetchVkPhone(string $vkId, string $accessToken): ?string
    {
        if (empty($accessToken)) {
            return null;
        }

        try {
            $resp = Http::timeout(8)->get('https://api.vk.ru/method/users.get', [
                'user_ids'     => $vkId,
                'fields'       => 'contacts',
                'access_token' => $accessToken,
                'v'            => '5.199',
            ]);

            if (!$resp->ok()) {
                Log::warning('VK phone fetch failed: http_not_ok', [
                    'vk_id'  => $vkId,
                    'status' => $resp->status(),
                ]);
                return null;
            }

            $data = $resp->json();
            $u = $data['response'][0] ?? null;

            if (!is_array($u)) {
                return null;
            }

            $mobile = $u['mobile_phone'] ?? null;
            $home   = $u['home_phone'] ?? null;

            $phone = $mobile ?: $home;
            $phone = is_string($phone) ? trim($phone) : null;

            return $phone ?: null;
        } catch (\Throwable $e) {
            Log::warning('VK phone fetch failed: exception', [
                'vk_id' => $vkId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function isValidPhone(string $phone): bool
    {
        $phone = trim($phone);

        if ($phone === '' || $phone === 'null' || $phone === 'false') {
            return false;
        }

        $clean = preg_replace('/(?!^\+)[^\d]/', '', $phone);

        if (preg_match('/^\+\d{10,15}$/', $clean)) return true;
        if (preg_match('/^\d{10,11}$/', $clean)) return true;
        if (preg_match('/^(\+7|7|8)\d{10}$/', $clean)) return true;

        return false;
    }
}
