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

final class YandexAuthController extends Controller
{
    /**
     * /auth/yandex/redirect          -> LOGIN
     * /auth/yandex/redirect?link=1   -> LINK
     */
    public function redirect(Request $request)
    {
        $isLink = $request->boolean('link');

        if ($isLink) {
            if (!Auth::check()) {
                return redirect()->route('login')->with('error', 'Сначала войдите в аккаунт, чтобы привязать Яндекс.');
            }

            $request->session()->put('linking_user_id', Auth::id());
            $request->session()->put('linking_provider', 'yandex');
        } else {
            // intent для UI/логики (auth_provider ставим только после успешного login)
            $request->session()->put('oauth_provider', 'yandex');
            $request->session()->put('oauth_intent', 'login');
        }

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
        $raw      = (array) $yaUser->user;

        $phone = $raw['default_phone']['number'] ?? $raw['phone']['number'] ?? null;

        // LINK?
        $linkingUserId   = $request->session()->pull('linking_user_id');
        $linkingProvider = $request->session()->pull('linking_provider');

        if (!empty($linkingUserId) && $linkingProvider === 'yandex') {
            $target = User::query()->find($linkingUserId);
            if (!$target) {
                return redirect()->route('login')->with('error', 'Не найден аккаунт для привязки Яндекса.');
            }

            $exists = User::query()
                ->where('yandex_id', $yandexId)
                ->where('id', '!=', $target->id)
                ->exists();

            if ($exists) {
                return redirect('/user/profile')->with('error', 'Этот Яндекс уже привязан к другому аккаунту.');
            }

            $target->yandex_id = $yandexId;

            if ($target->isFillable('yandex_avatar')) $target->yandex_avatar = $avatar;
            if ($target->isFillable('yandex_phone'))  $target->yandex_phone = $phone;

            if (empty($target->email) && !empty($email) && $target->isFillable('email')) {
                // не перетираем существующий email
                $target->email = $email;
            }

            $target->save();

            // аватар только если нет profile_photo_path
            $thumbPath = ProfilePhotoService::storeProviderAvatarIfMissing(
                userId: (int) $target->id,
                avatarUrl: $avatar,
                currentProfilePhotoPath: $target->profile_photo_path ?? null,
            );
            if (!empty($thumbPath)) {
                $target->profile_photo_path = $thumbPath;
                $target->save();
            }

            $request->session()->put('auth_provider', 'yandex');
            $request->session()->put('auth_provider_id', $yandexId);

            return redirect('/user/profile')->with('status', 'Яндекс привязан ✅');
        }

        // LOGIN
        $user = User::query()->where('yandex_id', $yandexId)->first();

        if (!$user) {
            $user = new User();

            if ($user->isFillable('name')) {
                $user->name = $name ?: 'Yandex user';
            }

            // users.email NOT NULL => нужен safe email всегда
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

            if ($user->isFillable('email')) {
                $user->email = $safeEmail;
            }

            if ($user->isFillable('password')) {
                $user->password = Hash::make(str()->random(32));
            }

            $user->yandex_id = $yandexId;
            if ($user->isFillable('yandex_avatar')) $user->yandex_avatar = $avatar;
            if ($user->isFillable('yandex_phone'))  $user->yandex_phone = $phone;

            $user->save();

            // аватар только если нет profile_photo_path
            $thumbPath = ProfilePhotoService::storeProviderAvatarIfMissing(
                userId: (int) $user->id,
                avatarUrl: $avatar,
                currentProfilePhotoPath: $user->profile_photo_path ?? null,
            );
            if (!empty($thumbPath)) {
                $user->profile_photo_path = $thumbPath;
                $user->save();
            }
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        $request->session()->put('auth_provider', 'yandex');
        $request->session()->put('auth_provider_id', $yandexId);

        return redirect()->intended('/events');
    }
}
