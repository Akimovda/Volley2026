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
    private function debug(): bool
    {
        return (bool) config('app.debug');
    }

    private function logDebug(string $msg, array $ctx = []): void
    {
        if (!$this->debug()) return;
        Log::debug('[VK_OAUTH] ' . $msg, $ctx);
    }

    private function logWarn(string $msg, array $ctx = []): void
    {
        if (!$this->debug()) return;
        Log::warning('[VK_OAUTH] ' . $msg, $ctx);
    }

    /**
     * Сохраняем безопасный return URL в сессии.
     */
    private function storeReturnTo(Request $request): void
    {
        $returnTo = (string) ($request->query('return') ?: url()->previous() ?: '');
        $returnTo = $this->sanitizeReturnTo($returnTo);
        $request->session()->put('oauth_return_to', $returnTo);
    }

    /**
     * Возвращаем только на наш домен, исключая /auth/* и /logout.
     */
    private function sanitizeReturnTo(string $url): string
    {
        $fallback = url('/events');

        $url = trim($url);
        if ($url === '') return $fallback;

        $appHost = parse_url(config('app.url'), PHP_URL_HOST);
        $urlHost = parse_url($url, PHP_URL_HOST);

        // если передали абсолютный URL на другой домен — запрещаем
        if ($urlHost && $appHost && strcasecmp($urlHost, $appHost) !== 0) {
            return $fallback;
        }

        // запрещаем циклы на oauth и logout
        if (str_contains($url, '/auth/') || str_contains($url, '/logout')) {
            return $fallback;
        }

        return $url;
    }

    private function popReturnTo(Request $request): string
    {
        return (string) $request->session()->pull('oauth_return_to', url('/events'));
    }

    /**
     * Redirect to VKID.
     * - сохраняем intent + return_to в сессию
     * - строго stateful
     */
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

    /**
     * Callback от VKID.
     * ВАЖНО: stateless не используем.
     */
    public function callback(Request $request)
    {
        $returnTo = $this->popReturnTo($request);

        try {
            $vkUser = Socialite::driver('vkid')->user();
        } catch (\Throwable $e) {
            $this->logWarn('callback failed (state/session lost?)', [
                'e' => $e->getMessage(),
                'has_code' => $request->filled('code'),
                'query_keys' => array_keys($request->query()),
            ]);

            $request->session()->forget(['oauth_intent', 'oauth_provider']);

            return redirect()->route('login')
                ->with('error', 'VK: сессия авторизации истекла/потерялась. Нажми “Войти через VK” ещё раз.');
        }

        $vkId        = (string) $vkUser->getId();
        $name        = (string) ($vkUser->getName() ?? '');
        $avatar      = $vkUser->getAvatar();
        $accessToken = (string) ($vkUser->token ?? '');

        // email: иногда лежит в accessTokenResponseBody['email']
        $vkEmail = (string) ($vkUser->getEmail() ?? '');
        $body = $vkUser->accessTokenResponseBody ?? null;
        if ($vkEmail === '' && is_array($body)) {
            $vkEmail = (string) ($body['email'] ?? '');
        }

        // Телефон: отдельный запрос (может не вернуться)
        $vkPhone = $this->fetchVkPhone($vkId, $accessToken);
        if (!empty($vkPhone) && !$this->isValidPhone($vkPhone)) {
            $vkPhone = null;
        }

        $intent = (string) $request->session()->pull('oauth_intent', Auth::check() ? 'link' : 'login');
        $request->session()->forget('oauth_provider');

        // -------------------------
        // LINK
        // -------------------------
        if ($intent === 'link') {
            if (!Auth::check()) {
                return redirect()->route('login')->with('error', 'Сначала войдите, чтобы привязать VK.');
            }

            /** @var User $current */
            $current = $request->user();

            $existsForOther = User::query()
                ->where('vk_id', $vkId)
                ->where('id', '!=', $current->id)
                ->exists();

            if ($existsForOther) {
                return redirect('/user/profile')->with('error', 'Этот VK уже привязан к другому аккаунту.');
            }

            if ($current->isFillable('vk_id')) {
                $current->vk_id = $vkId;
            }
            if ($current->isFillable('vk_email') && $vkEmail !== '' && empty($current->vk_email)) {
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

            UserPhotoFromProviderService::seedFromProviderIfAllowed($current, $avatar, false);

            $request->session()->put('auth_provider', 'vk');
            $request->session()->put('auth_provider_id', $vkId);

            return redirect()->to($returnTo)->with('status', 'VK привязан ✅');
        }

        // -------------------------
        // LOGIN
        // -------------------------
        $user = User::query()->where('vk_id', $vkId)->first();

        // (опционально) привязка по email
        if (!$user && $vkEmail !== '') {
            $candidate = User::query()->where('email', $vkEmail)->first();
            if ($candidate) {
                $existsForOther = User::query()
                    ->where('vk_id', $vkId)
                    ->where('id', '!=', $candidate->id)
                    ->exists();

                if ($existsForOther) {
                    return redirect()->route('login')->with('error', 'Этот VK уже привязан к другому аккаунту.');
                }

                if ($candidate->isFillable('vk_id') && empty($candidate->vk_id)) {
                    $candidate->vk_id = $vkId;
                }
                if ($candidate->isFillable('vk_email') && $vkEmail !== '' && empty($candidate->vk_email)) {
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
            $isNewUser = true;

            if ($user->isFillable('name')) $user->name = $displayName;
            if ($user->isFillable('email')) $user->email = $safeEmail;
            if ($user->isFillable('password')) $user->password = Hash::make(Str::random(32));

            if ($user->isFillable('vk_id')) $user->vk_id = $vkId;
            if ($user->isFillable('vk_email') && $vkEmail !== '') $user->vk_email = $vkEmail;

            if (!empty($vkPhone)) {
                if ($user->isFillable('phone')) {
                    $user->phone = $vkPhone;
                } elseif ($user->isFillable('vk_phone')) {
                    $user->vk_phone = $vkPhone;
                }
            }

            $user->save();
        } else {
            // при логине: можно добить телефон только если пусто
            if (!empty($vkPhone)) {
                $changed = false;
                if ($user->isFillable('phone') && empty($user->phone)) {
                    $user->phone = $vkPhone;
                    $changed = true;
                } elseif ($user->isFillable('vk_phone') && empty($user->vk_phone)) {
                    $user->vk_phone = $vkPhone;
                    $changed = true;
                }
                if ($changed) $user->save();
            }
        }

        UserPhotoFromProviderService::seedFromProviderIfAllowed($user, $avatar, $isNewUser);

        Auth::login($user, true);
        $request->session()->regenerate();

        $request->session()->put('auth_provider', 'vk');
        $request->session()->put('auth_provider_id', $vkId);

        return redirect()->to($returnTo);
    }

    /**
     * Добираем телефон после callback через VK API.
     * Не логируем access_token и PII; логируем только debug и без телефона.
     */
    private function fetchVkPhone(string $vkId, string $accessToken): ?string
    {
        if ($accessToken === '') return null;

        try {
            $resp = Http::timeout(8)->get('https://api.vk.ru/method/users.get', [
                'user_ids'     => $vkId,
                'fields'       => 'contacts',
                'access_token' => $accessToken,
                'v'            => '5.199',
            ]);

            if (!$resp->ok()) {
                $this->logWarn('phone fetch http_not_ok', [
                    'vk_id' => $vkId,
                    'status' => $resp->status(),
                ]);
                return null;
            }

            $data = $resp->json();
            $u = $data['response'][0] ?? null;
            if (!is_array($u)) return null;

            $mobile = $u['mobile_phone'] ?? null;
            $home   = $u['home_phone'] ?? null;

            $phone = $mobile ?: $home;
            $phone = is_string($phone) ? trim($phone) : null;

            return $phone ?: null;
        } catch (\Throwable $e) {
            $this->logWarn('phone fetch exception', [
                'vk_id' => $vkId,
                'e' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function isValidPhone(string $phone): bool
    {
        $phone = trim($phone);
        if ($phone === '' || $phone === 'null' || $phone === 'false') return false;

        $clean = preg_replace('/(?!^\+)[^\d]/', '', $phone);

        if (preg_match('/^\+\d{10,15}$/', $clean)) return true;
        if (preg_match('/^\d{10,11}$/', $clean)) return true;
        if (preg_match('/^(\+7|7|8)\d{10}$/', $clean)) return true;

        return false;
    }
}
