<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\UserPhotoFromProviderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class YandexAuthController extends Controller
{
    public function redirect(Request $request)
    {
        $intent = Auth::check() ? 'link' : 'login';
        $request->session()->put('oauth_provider', 'yandex');
        $request->session()->put('oauth_intent', $intent);

        return Socialite::driver('yandex')
            ->scopes(['login:email', 'login:info'])
            ->redirect();
    }

    public function callback(Request $request)
    {
        // Для Yandex обычно state работает нормально, stateless не используем.
        $yaUser = Socialite::driver('yandex')->user();

        $yandexId = (string) $yaUser->getId();
        $name     = (string) ($yaUser->getName() ?? '');
        $email    = (string) ($yaUser->getEmail() ?? '');
        $avatar   = $yaUser->getAvatar();

        $intent = (string) $request->session()->pull('oauth_intent', Auth::check() ? 'link' : 'login');
        $request->session()->forget('oauth_provider');

        // -------------------------
        // LINK
        // -------------------------
        if ($intent === 'link') {
            if (!Auth::check()) {
                return redirect()->route('login')->with('error', 'Сначала войдите, чтобы привязать Яндекс.');
            }

            /** @var User $current */
            $current = $request->user();

            $existsForOther = User::query()
                ->where('yandex_id', $yandexId)
                ->where('id', '!=', $current->id)
                ->exists();

            if ($existsForOther) {
                return redirect('/user/profile')->with('error', 'Этот Яндекс уже привязан к другому аккаунту.');
            }

            if ($current->isFillable('yandex_id')) {
                $current->yandex_id = $yandexId;
            }
            if ($current->isFillable('yandex_email') && $email !== '' && empty($current->yandex_email)) {
                $current->yandex_email = $email;
            }

            $current->save();

            UserPhotoFromProviderService::seedFromProviderIfAllowed($current, $avatar, false);

            $request->session()->put('auth_provider', 'yandex');
            $request->session()->put('auth_provider_id', $yandexId);

            return redirect('/user/profile')->with('status', 'Яндекс привязан ✅');
        }

        // -------------------------
        // LOGIN
        // -------------------------
        $user = User::query()->where('yandex_id', $yandexId)->first();

        // (опционально) если пришёл email — можно привязать существующего по email
        if (!$user && $email !== '') {
            $candidate = User::query()->where('email', $email)->first();
            if ($candidate) {
                $existsForOther = User::query()
                    ->where('yandex_id', $yandexId)
                    ->where('id', '!=', $candidate->id)
                    ->exists();

                if ($existsForOther) {
                    return redirect()->route('login')->with('error', 'Этот Яндекс уже привязан к другому аккаунту.');
                }

                if ($candidate->isFillable('yandex_id') && empty($candidate->yandex_id)) {
                    $candidate->yandex_id = $yandexId;
                }
                if ($candidate->isFillable('yandex_email') && $email !== '' && empty($candidate->yandex_email)) {
                    $candidate->yandex_email = $email;
                }

                $candidate->save();
                $user = $candidate;
            }
        }

        $isNewUser = false;

        if (!$user) {
            $displayName = $name ?: "Yandex User #{$yandexId}";

            // users.email NOT NULL — нужен safeEmail
            $safeEmail = null;
            if ($email !== '' && !User::query()->where('email', $email)->exists()) {
                $safeEmail = $email;
            }
            if (!$safeEmail) {
                $safeEmail = "yandex_{$yandexId}@yandex.local";
                if (User::query()->where('email', $safeEmail)->exists()) {
                    $safeEmail = "yandex_{$yandexId}_" . Str::random(6) . "@yandex.local";
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
            if ($user->isFillable('yandex_id')) {
                $user->yandex_id = $yandexId;
            }
            if ($user->isFillable('yandex_email') && $email !== '') {
                $user->yandex_email = $email;
            }

            $user->save();
        }

        UserPhotoFromProviderService::seedFromProviderIfAllowed($user, $avatar, $isNewUser);

        Auth::login($user, true);
        $request->session()->regenerate();

        $request->session()->put('auth_provider', 'yandex');
        $request->session()->put('auth_provider_id', $yandexId);

        return redirect()->intended('/events');
    }
}
