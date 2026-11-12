<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AccountLink;
use App\Models\User;
use App\Support\UserPhotoFromProviderService;
use App\Http\Controllers\Auth\Concerns\OAuthCallbackSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    use OAuthCallbackSupport;

    private function logWarn(string $msg, array $ctx = []): void
    {
        Log::warning('[GOOGLE_OAUTH] ' . $msg, $ctx);
    }

    public function redirect(Request $request)
    {
        $intent = Auth::check() ? 'link' : 'login';
        $this->storeReturnTo($request);
        $request->session()->put('oauth_provider', 'google');
        $request->session()->put('oauth_intent', $intent);

        // stateless: WKWebView (Capacitor) и Telegram не сохраняют session через OAuth-редирект
        $response = Socialite::driver('google')->stateless()->redirect();
        $request->session()->save();
        return $response;
    }

    public function callback(Request $request)
    {
        if ($dup = $this->duplicateCallbackRedirect($request)) {
            return $dup;
        }

        $returnTo = $this->popReturnTo($request);

        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (\Throwable $e) {
            $this->logWarn('callback failed', [
                'e'     => $e->getMessage(),
                'class' => get_class($e),
            ]);
            return $this->authFail('google', 'Google: ошибка авторизации. Попробуйте ещё раз.');
        }

        $googleId = (string) $googleUser->getId();
        $email    = (string) ($googleUser->getEmail() ?: '');
        $avatar   = $googleUser->getAvatar();

        $intent = (string) $request->session()->pull('oauth_intent', Auth::check() ? 'link' : 'login');

        /* LINK */
        if ($intent === 'link') {
            if (!Auth::check()) {
                return redirect()->route('login')->with('error', __('Сначала войдите, чтобы привязать Google.'));
            }

            $current = $request->user();

            if (User::where('google_id', $googleId)->where('id', '!=', $current->id)->exists()
                || AccountLink::isTaken('google', $googleId, $current->id)) {
                return redirect('/user/profile')->with('error', __('Этот Google аккаунт уже привязан к другому пользователю.'));
            }

            $current->google_id = $googleId;
            $current->save();

            UserPhotoFromProviderService::seedFromProviderIfAllowed($current, $avatar, false);

            return redirect()->to($returnTo)->with('status', __('Google привязан ✅'));
        }

        /* LOGIN */
        $user = User::where('google_id', $googleId)->first();

        // Привязка к существующему аккаунту по email — только если Google подтвердил этот адрес (email_verified),
        // и только к аккаунту без другого Google (чужую привязку не перехватываем)
        $rawGoogle     = (array) ($googleUser->user ?? []);
        $emailVerified = $email !== '' && filter_var($rawGoogle['email_verified'] ?? ($rawGoogle['verified_email'] ?? false), FILTER_VALIDATE_BOOLEAN);

        if (!$user && $emailVerified) {
            $candidate = User::where('email', $email)->first();
            if ($candidate && (empty($candidate->google_id) || (string) $candidate->google_id === $googleId)) {
                if (User::where('google_id', $googleId)->where('id', '!=', $candidate->id)->exists()) {
                    return redirect()->route('login')->with('error', __('Этот Google аккаунт уже привязан к другому пользователю.'));
                }
                $candidate->google_id = $googleId;
                $candidate->save();
                $user = $candidate;
            }
        }

        if (!$user) {
            // Провайдер id мог остаться алиасом на другого пользователя после merge
            // (UserMergeService, primary уже владел своим google_id) — резолвим туда.
            $user = AccountLink::resolveUser('google', $googleId);
        }

        $isNewUser = false;

        if (!$user) {
            $isNewUser = true;

            $user = $this->createOAuthUser('google_id', $googleId, trim($googleUser->getName() ?: 'Пользователь'), $emailVerified ? $email : '', "google_{$googleId}@google.local");
            $isNewUser = $user->wasRecentlyCreated; // false — пользователя уже создал параллельный запрос
        }

        Auth::login($user, true);
        $request->session()->regenerate();
        $request->session()->put('auth_provider', 'google');
        $request->session()->put('auth_provider_id', $googleId);
        $this->authSuccess('google', $isNewUser);

        UserPhotoFromProviderService::seedFromProviderIfAllowed($user, $avatar, $isNewUser);

        if ($isNewUser) {
            return redirect()->route('profile.complete')->with('welcome', true);
        }

        return redirect()->to($returnTo);
    }
}
