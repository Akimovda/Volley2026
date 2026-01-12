<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ProfilePhotoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;

class YandexAuthController extends Controller
{
    /**
     * ЕДИНООБРАЗНЫЙ redirect():
     * - записываем oauth_provider / oauth_intent
     * - auth_provider НЕ трогаем
     */
    public function redirect(Request $request)
    {
        $isLink = $request->boolean('link');

        if ($isLink && !Auth::check()) {
            return redirect()->route('login')->with('error', 'Сначала войдите, чтобы привязать Яндекс.');
        }

        $request->session()->put('oauth_provider', 'yandex');
        $request->session()->put('oauth_intent', ($isLink && Auth::check()) ? 'link' : 'login');

        return Socialite::driver('yandex')->redirect();
    }

    public function callback(Request $request)
    {
        try {
            $yaUser = Socialite::driver('yandex')->user();
        } catch (\Laravel\Socialite\Two\InvalidStateException $e) {
            $yaUser = Socialite::driver('yandex')->stateless()->user();
        }

        $yandexId = (string) $yaUser->getId();
        $email    = $yaUser->getEmail();
        $name     = $yaUser->getName() ?: ($yaUser->getNickname() ?: null);
        $avatar   = $yaUser->getAvatar();

        $intent = (string) $request->session()->pull('oauth_intent', Auth::check() ? 'link' : 'login');
        $request->session()->forget('oauth_provider');

        // =========================
        // LINK
        // =========================
        if ($intent === 'link' && Auth::check()) {
            /** @var User $current */
            $current = Auth::user();

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

            if ($current->isFillable('yandex_email') && !empty($email) && empty($current->yandex_email)) {
                $current->yandex_email = $email;
            }

            if ($current->isFillable('yandex_avatar') && !empty($avatar) && empty($current->yandex_avatar)) {
                $current->yandex_avatar = $avatar;
            }

            // avatar -> только если у юзера ещё нет profile_photo_path
            $baseName = ProfilePhotoService::storeProviderAvatarBasenameIfMissing(
                userId: (int) $current->id,
                avatarUrl: $avatar,
                currentProfilePhotoPath: $current->profile_photo_path ?? null
            );
            if (!empty($baseName) && $current->isFillable('profile_photo_path')) {
                $current->profile_photo_path = $baseName; // В БД: "av-{id}"
            }

            $current->save();

            $request->session()->put('auth_provider', 'yandex');
            $request->session()->put('auth_provider_id', $yandexId);

            return redirect('/user/profile')->with('status', 'Яндекс привязан ✅');
        }

        // =========================
        // LOGIN
        // =========================
        $user = User::query()->where('yandex_id', $yandexId)->first();

        if (!$user) {
            $user = new User();

            $displayName = $name ?: "Yandex User #{$yandexId}";

            // users.email NOT NULL -> безопасный email
            $safeEmail = null;
            if (!empty($email) && !User::where('email', $email)->exists()) {
                $safeEmail = $email;
            }
            if (empty($safeEmail)) {
                $safeEmail = "ya_{$yandexId}@yandex.local";
                if (User::where('email', $safeEmail)->exists()) {
                    $safeEmail = "ya_{$yandexId}_" . now()->timestamp . "@yandex.local";
                }
            }

            if ($user->isFillable('name')) $user->name = $displayName;
            if ($user->isFillable('email')) $user->email = $safeEmail;
            if ($user->isFillable('password')) $user->password = Hash::make(str()->random(32));

            if ($user->isFillable('yandex_id')) $user->yandex_id = $yandexId;
            if ($user->isFillable('yandex_email') && !empty($email)) $user->yandex_email = $email;
            if ($user->isFillable('yandex_avatar') && !empty($avatar)) $user->yandex_avatar = $avatar;

            $user->save();
        }

        // avatar -> только если пусто
        $baseName = ProfilePhotoService::storeProviderAvatarBasenameIfMissing(
            userId: (int) $user->id,
            avatarUrl: $avatar,
            currentProfilePhotoPath: $user->profile_photo_path ?? null
        );
        if (!empty($baseName) && $user->isFillable('profile_photo_path')) {
            $user->profile_photo_path = $baseName; // В БД: "av-{id}"
            $user->save();
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        $request->session()->put('auth_provider', 'yandex');
        $request->session()->put('auth_provider_id', $yandexId);

        return redirect()->intended('/events');
    }
}
