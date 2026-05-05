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

class GoogleAuthController extends Controller
{
    private function logWarn(string $msg, array $ctx = []): void
    {
        Log::warning('[GOOGLE_OAUTH] ' . $msg, $ctx);
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
        $request->session()->put('oauth_provider', 'google');
        $request->session()->put('oauth_intent', $intent);

        $response = Socialite::driver('google')->redirect();
        $request->session()->save();
        return $response;
    }

    public function callback(Request $request)
    {
        $returnTo = $this->popReturnTo($request);

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            $this->logWarn('callback failed', [
                'e'     => $e->getMessage(),
                'class' => get_class($e),
            ]);
            return redirect()->route('login')->with('error', 'Google: ошибка авторизации. Попробуйте ещё раз.');
        }

        $googleId = (string) $googleUser->getId();
        $email    = (string) ($googleUser->getEmail() ?: '');
        $avatar   = $googleUser->getAvatar();

        $intent = (string) $request->session()->pull('oauth_intent', Auth::check() ? 'link' : 'login');

        /* LINK */
        if ($intent === 'link') {
            if (!Auth::check()) {
                return redirect()->route('login')->with('error', 'Сначала войдите, чтобы привязать Google.');
            }

            $current = $request->user();

            if (User::where('google_id', $googleId)->where('id', '!=', $current->id)->exists()) {
                return redirect('/user/profile')->with('error', 'Этот Google аккаунт уже привязан к другому пользователю.');
            }

            $current->google_id = $googleId;
            $current->save();

            UserPhotoFromProviderService::seedFromProviderIfAllowed($current, $avatar, false);

            return redirect()->to($returnTo)->with('status', 'Google привязан ✅');
        }

        /* LOGIN */
        $user = User::where('google_id', $googleId)->first();

        if (!$user && $email) {
            $user = User::where('email', $email)->first();
            if ($user) {
                if (User::where('google_id', $googleId)->where('id', '!=', $user->id)->exists()) {
                    return redirect()->route('login')->with('error', 'Этот Google аккаунт уже привязан к другому пользователю.');
                }
                $user->google_id = $googleId;
                $user->save();
            }
        }

        $isNewUser = false;

        if (!$user) {
            $isNewUser = true;

            $safeEmail = $email ?: "google_{$googleId}@google.local";
            if (User::where('email', $safeEmail)->exists()) {
                $safeEmail = "google_{$googleId}_" . Str::random(6) . "@google.local";
            }

            $user = new User();
            $user->email    = $safeEmail;
            $user->password = Hash::make(Str::random(32));
            $user->name     = trim($googleUser->getName() ?: 'Пользователь');
            $user->google_id = $googleId;
            $user->save();
        }

        Auth::login($user, true);
        $request->session()->regenerate();
        $request->session()->put('auth_provider', 'google');
        $request->session()->put('auth_provider_id', $googleId);

        UserPhotoFromProviderService::seedFromProviderIfAllowed($user, $avatar, $isNewUser);

        if ($isNewUser) {
            return redirect()->route('profile.complete')->with('welcome', true);
        }

        return redirect()->to($returnTo);
    }
}
