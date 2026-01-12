<?php

declare(strict_types=1);

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
     * ------------------------------------------------------------
     * REDIRECT (единый контракт)
     * ------------------------------------------------------------
     * - пишет только oauth_provider/oauth_intent
     * - НЕ трогает auth_provider
     */
    public function redirect(Request $request)
    {
        $isLink = $request->boolean('link');

        $request->session()->put('oauth_provider', 'yandex');
        $request->session()->put('oauth_intent', ($isLink && Auth::check()) ? 'link' : 'login');

        if ($isLink && !Auth::check()) {
            return redirect()->route('login')->with('error', 'Сначала войдите в аккаунт, чтобы привязать Яндекс.');
        }

        return Socialite::driver('yandex')->redirect();
    }

    /**
     * ------------------------------------------------------------
     * CALLBACK (единый login/link)
     * ------------------------------------------------------------
     */
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

        $raw   = (array) $yaUser->user;
        $phone = $raw['default_phone']['number'] ?? $raw['phone']['number'] ?? null;

        $intent = (string) $request->session()->get('oauth_intent', Auth::check() ? 'link' : 'login');

        // =========================
        // MODE: LINK
        // =========================
        if ($intent === 'link') {
            if (!Auth::check()) {
                return redirect()->route('login')->with('error', 'Сессия истекла. Войдите и повторите привязку Яндекса.');
            }

            /** @var User $current */
            $current = Auth::user();

            $existsForOther = User::query()
                ->where('yandex_id', $yandexId)
                ->where('id', '!=', $current->id)
                ->exists();

            if ($existsForOther) {
                return redirect('/user/profile')->with('error', 'Этот Яндекс уже привязан к другому аккаунту.');
            }

            $current->yandex_id = $yandexId;

            if ($current->isFillable('yandex_avatar')) $current->yandex_avatar = $avatar;
            if ($current->isFillable('yandex_phone'))  $current->yandex_phone  = $phone;

            // не перетираем email, только если пустой
            if (empty($current->email) && !empty($email)) {
                $current->email = $email;
            }

            // avatar only if missing
            $thumbPath = ProfilePhotoService::storeProviderAvatarIfMissing(
                userId: (int) $current->id,
                avatarUrl: $avatar ?: null,
                currentProfilePhotoPath: $current->profile_photo_path ?? null,
            );
            if (!empty($thumbPath)) {
                $current->profile_photo_path = $thumbPath;
            }

            $current->save();

            $request->session()->put('auth_provider', 'yandex');
            $request->session()->put('auth_provider_id', $yandexId);

            return redirect('/user/profile')->with('status', 'Яндекс привязан ✅');
        }

        // =========================
        // MODE: LOGIN
        // =========================
        $user = User::query()->where('yandex_id', $yandexId)->first();

        if (!$user) {
            $user = new User();

            if ($user->isFillable('name')) {
                $user->name = $name ?: 'Yandex user';
            }

            // users.email NOT NULL => безопасный email
            $safeEmail = null;

            if (!empty($email) && !User::query()->where('email', $email)->exists()) {
                $safeEmail = $email;
            }

            if (empty($safeEmail)) {
                $safeEmail = "ya_{$yandexId}@yandex.local";
                if (User::query()->where('email', $safeEmail)->exists()) {
                    $safeEmail = "ya_{$yandexId}_" . now()->timestamp . "@yandex.local";
                }
            }

            if ($user->isFillable('email')) {
                $user->email = $safeEmail;
            }

            if ($user->isFillable('password')) {
                $user->password = Hash::make(str()->random(32));
            }

            $user->yandex_id = $yandexId;

            if ($user->isFillable('yandex_avatar')) $user->yandex_avatar = $avatar;
            if ($user->isFillable('yandex_phone'))  $user->yandex_phone  = $phone;

            $user->save();
        }

        // avatar only if missing
        $thumbPath = ProfilePhotoService::storeProviderAvatarIfMissing(
            userId: (int) $user->id,
            avatarUrl: $avatar ?: null,
            currentProfilePhotoPath: $user->profile_photo_path ?? null,
        );
        if (!empty($thumbPath)) {
            $user->profile_photo_path = $thumbPath;
            $user->save();
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        $request->session()->put('auth_provider', 'yandex');
        $request->session()->put('auth_provider_id', $yandexId);

        return redirect()->intended('/events');
    }
}
