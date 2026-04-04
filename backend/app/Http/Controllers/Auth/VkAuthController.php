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
    /* -------------------------------------------------
     | Helpers
     * -------------------------------------------------*/

    private function debug(): bool
    {
        return (bool) config('app.debug');
    }

    private function logDebug(string $msg, array $ctx = []): void
    {
        if ($this->debug()) {
            Log::debug('[VK_OAUTH] ' . $msg, $ctx);
        }
    }

    private function logWarn(string $msg, array $ctx = []): void
    {
        if ($this->debug()) {
            Log::warning('[VK_OAUTH] ' . $msg, $ctx);
        }
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

    /* -------------------------------------------------
     | Redirect
     * -------------------------------------------------*/

    public function redirect(Request $request)
    {
        $intent = Auth::check() ? 'link' : 'login';

        $this->storeReturnTo($request);

        $request->session()->put('oauth_provider', 'vk');
        $request->session()->put('oauth_intent', $intent);

        return Socialite::driver('vkid')
            ->scopes(['email'])
            ->redirect();
    }

    /* -------------------------------------------------
     | Callback
     * -------------------------------------------------*/

    public function callback(Request $request)
    {
        $returnTo = $this->popReturnTo($request);

        try {
            $vkUser = Socialite::driver('vkid')->user();
        } catch (\Throwable $e) {
            $this->logWarn('callback failed', ['e' => $e->getMessage()]);
            return redirect()->route('login')
                ->with('error', 'VK: сессия авторизации истекла. Попробуйте ещё раз.');
        }

        $vkId   = (string) $vkUser->getId();
        $name   = (string) ($vkUser->getName() ?? '');
        $avatar = $vkUser->getAvatar();

        $vkEmail = (string) ($vkUser->getEmail() ?? '');
        $body = $vkUser->accessTokenResponseBody ?? [];
        if ($vkEmail === '' && is_array($body)) {
            $vkEmail = (string) ($body['email'] ?? '');
        }

        $vkPhone = $this->fetchVkPhone(
            (string) $vkId,
            (string) ($vkUser->token ?? '')
        );

        $intent = (string) $request->session()->pull(
            'oauth_intent',
            Auth::check() ? 'link' : 'login'
        );

        /* -------------------------------------------------
         | LINK
         * -------------------------------------------------*/
        if ($intent === 'link') {
            if (!Auth::check()) {
                return redirect()->route('login')
                    ->with('error', 'Сначала войдите, чтобы привязать VK.');
            }

            /** @var User $current */
            $current = $request->user();

            $exists = User::query()
                ->where('vk_id', $vkId)
                ->where('id', '!=', $current->id)
                ->exists();

            if ($exists) {
                return redirect('/user/profile')
                    ->with('error', 'Этот VK уже привязан к другому аккаунту.');
            }

            // OAuth-поля — ТОЛЬКО напрямую
            $current->vk_id = $vkId;

            if ($vkEmail !== '' && empty($current->vk_email)) {
                $current->vk_email = $vkEmail;
            }

            if (!empty($vkPhone) && empty($current->vk_phone)) {
                $current->vk_phone = $vkPhone;
            }

            $current->save();

            UserPhotoFromProviderService::seedFromProviderIfAllowed(
                $current,
                $avatar,
                false
            );

            return redirect()->to($returnTo)->with('status', 'VK привязан ✅');
        }
        /* -------------------------------------------------
         | LOGIN
         * -------------------------------------------------*/
        $user = User::query()->where('vk_id', $vkId)->first();

        // fallback: если вход ещё не привязан, но VK-уведомления уже подключены,
        // считаем, что это тот же пользователь, и дозаписываем vk_id
        if (!$user) {
            $user = User::query()
                ->where('vk_notify_user_id', $vkId)
                ->first();

            if ($user) {
                $exists = User::query()
                    ->where('vk_id', $vkId)
                    ->where('id', '!=', $user->id)
                    ->exists();

                if ($exists) {
                    return redirect()->route('login')
                        ->with('error', 'Этот VK уже привязан к другому аккаунту.');
                }

                $user->vk_id = $vkId;

                if ($vkEmail !== '' && empty($user->vk_email)) {
                    $user->vk_email = $vkEmail;
                }

                if (!empty($vkPhone) && empty($user->phone)) {
                    $user->phone = $vkPhone;
                }

                $user->save();
            }
        }

        // привязка по email
        if (!$user && $vkEmail !== '') {
            $candidate = User::query()->where('email', $vkEmail)->first();

            if ($candidate) {
                $exists = User::query()
                    ->where('vk_id', $vkId)
                    ->where('id', '!=', $candidate->id)
                    ->exists();

                if ($exists) {
                    return redirect()->route('login')
                        ->with('error', 'Этот VK уже привязан к другому аккаунту.');
                }

                if (empty($candidate->vk_id)) {
                    $candidate->vk_id = $vkId;
                }

                if ($vkEmail !== '' && empty($candidate->vk_email)) {
                    $candidate->vk_email = $vkEmail;
                }

                if (!empty($vkPhone) && empty($candidate->phone)) {
                    $candidate->phone = $vkPhone;
                }

                $candidate->save();
                $user = $candidate;
            }
        }

        $isNewUser = false;

        if (!$user) {
            $isNewUser = true;

            $displayName = $name !== '' ? $name : "VK User #{$vkId}";
            $safeEmail = null;

            if ($vkEmail !== '' && !User::query()->where('email', $vkEmail)->exists()) {
                $safeEmail = $vkEmail;
            }

            if (!$safeEmail) {
                $safeEmail = "vk_{$vkId}@vk.local";

                if (User::query()->where('email', $safeEmail)->exists()) {
                    $safeEmail = "vk_{$vkId}_" . Str::random(6) . "@vk.local";
                }
            }

            $user = new User();
            $user->name = $displayName;
            $user->email = $safeEmail;
            $user->password = Hash::make(Str::random(32));
            $user->vk_id = $vkId;

            if ($vkEmail !== '') {
                $user->vk_email = $vkEmail;
            }

            if (!empty($vkPhone)) {
                $user->phone = $vkPhone;
            }

            $user->save();
        } else {
            // мягко добиваем телефон
            if (!empty($vkPhone) && empty($user->phone)) {
                $user->phone = $vkPhone;
                $user->save();
            }
        }

        UserPhotoFromProviderService::seedFromProviderIfAllowed(
            $user,
            $avatar,
            $isNewUser
        );

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->to($returnTo);
    }    
    /* -------------------------------------------------
     | VK API helpers
     * -------------------------------------------------*/

    private function fetchVkPhone(string $vkId, string $accessToken): ?string
    {
        if ($accessToken === '') return null;

        try {
            $resp = Http::timeout(8)->get(
                'https://api.vk.ru/method/users.get',
                [
                    'user_ids'     => $vkId,
                    'fields'       => 'contacts',
                    'access_token' => $accessToken,
                    'v'            => '5.199',
                ]
            );

            if (!$resp->ok()) {
                return null;
            }

            $data = $resp->json();
            $u = $data['response'][0] ?? null;
            if (!is_array($u)) return null;

            $phone = $u['mobile_phone'] ?? $u['home_phone'] ?? null;
            $phone = is_string($phone) ? trim($phone) : null;

            return $this->isValidPhone($phone) ? $phone : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function isValidPhone(?string $phone): bool
    {
        if (!$phone) return false;

        $clean = preg_replace('/(?!^\+)[^\d]/', '', $phone);

        return
            preg_match('/^\+\d{10,15}$/', $clean) ||
            preg_match('/^\d{10,11}$/', $clean) ||
            preg_match('/^(\+7|7|8)\d{10}$/', $clean);
    }
}