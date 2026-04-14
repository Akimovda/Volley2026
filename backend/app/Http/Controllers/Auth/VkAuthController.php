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

class VkAuthController extends Controller
{
    private function debug(): bool
    {
        return (bool) config('app.debug');
    }

    private function logWarn(string $msg, array $ctx = []): void
    {
        if ($this->debug()) Log::warning('[VK_OAUTH] ' . $msg, $ctx);
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
        $intent = Auth::check() ? 'link' : 'login';
        $this->storeReturnTo($request);
        $request->session()->put('oauth_provider', 'vk');
        $request->session()->put('oauth_intent', $intent);
        return Socialite::driver('vkid')->redirect();
    }

    public function callback(Request $request)
    {
        $returnTo = $this->popReturnTo($request);

        try {
            $vkUser = Socialite::driver('vkid')->user();
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
            $user->name     = 'Пользователь';
            $user->email    = $safeEmail;
            $user->password = Hash::make(Str::random(32));
            $user->vk_id    = $vkId;
            $user->save();
        }

        UserPhotoFromProviderService::seedFromProviderIfAllowed($user, $avatar, $isNewUser);

        Auth::login($user, true);
        $request->session()->regenerate();

        if ($isNewUser) {
            return redirect()->route('profile.complete')->with('welcome', true);
        }

        return redirect()->to($returnTo);
    }
}
