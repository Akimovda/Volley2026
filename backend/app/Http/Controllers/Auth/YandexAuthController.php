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
     * /auth/yandex/redirect          -> LOGIN
     * /auth/yandex/redirect?link=1   -> LINK
     */
    public function redirect(Request $request)
    {
        $isLink = $request->boolean('link');

        if ($isLink) {
            if (!Auth::check()) {
                return redirect()->route('login')
                    ->with('error', 'Сначала войдите в аккаунт, чтобы привязать Яндекс.');
            }

            // LINK: запоминаем кому привязываем
            $request->session()->put('linking_user_id', Auth::id());
            $request->session()->put('linking_provider', 'yandex');
            $request->session()->put('oauth_provider', 'yandex');
            $request->session()->put('oauth_intent', 'link');
        } else {
            // LOGIN: intent для UI
            $request->session()->put('oauth_provider', 'yandex');
            $request->session()->put('oauth_intent', 'login');
        }

        // auth_provider НЕ трогаем здесь
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

        $phone = $raw['default_phone']['number'] ?? ($raw['phone']['number'] ?? null);

        // ===== LINK MODE =====
        $linkingUserId   = $request->session()->pull('linking_user_id');
        $linkingProvider = $request->session()->pull('linking_provider');

        if (!empty($linkingUserId) && $linkingProvider === 'yandex') {
            $target = User::query()->find($linkingUserId);

            if (!$target) {
                return redirect()->route('login')
                    ->with('error', 'Не найден аккаунт для привязки Яндекса.');
            }

            $exists = User::query()
                ->where('yandex_id', $yandexId)
                ->where('id', '!=', $target->id)
                ->exists();

            if ($exists) {
                return redirect('/user/profile')
                    ->with('error', 'Этот Яндекс уже привязан к другому аккаунту.');
            }

            $target->yandex_id = $yandexId;

            if ($target->isFillable('yandex_avatar')) $target->yandex_avatar = $avatar;
            if ($target->isFillable('yandex_phone'))  $target->yandex_phone  = $phone;
            if ($target->isFillable('yandex_email') && !empty($email)) $target->yandex_email = $email;

            // email НЕ перетираем, только если пустой
            if (empty($target->email) && !empty($email)) {
                $target->email = $email;
            }

            // Аватар — только если ещё нет profile_photo_path
            if (empty($target->profile_photo_path)) {
                $thumbPath = ProfilePhotoService::storeProviderAvatarIfMissing(
                    userId: (int) $target->id,
                    avatarUrl: $avatar ?: null,
                    currentProfilePhotoPath: $target->profile_photo_path
                );

                if (!empty($thumbPath)) {
                    $target->profile_photo_path = $thumbPath;
                }
            }

            $target->save();

            // auth_provider ставим ТОЛЬКО на успехе
            $request->session()->put('auth_provider', 'yandex');
            $request->session()->put('auth_provider_id', $yandexId);

            return redirect('/user/profile')->with('status', 'Яндекс привязан ✅');
        }

        // ===== LOGIN MODE =====
        $user = User::query()->where('yandex_id', $yandexId)->first();

        if (!$user) {
            $user = new User();

            $user->name = $name ?: 'Yandex user';

            // users.email NOT NULL => всегда заполняем
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
            $user->email = $safeEmail;

            // пароль (чтобы Fortify/Jetstream не ругались)
            $user->password = Hash::make(str()->random(32));

            $user->yandex_id = $yandexId;

            if ($user->isFillable('yandex_avatar')) $user->yandex_avatar = $avatar;
            if ($user->isFillable('yandex_phone'))  $user->yandex_phone  = $phone;
            if ($user->isFillable('yandex_email') && !empty($email)) $user->yandex_email = $email;

            $user->save();

            // avatar — только если profile_photo_path пуст
            if (empty($user->profile_photo_path)) {
                $thumbPath = ProfilePhotoService::storeProviderAvatarIfMissing(
                    userId: (int) $user->id,
                    avatarUrl: $avatar ?: null,
                    currentProfilePhotoPath: $user->profile_photo_path
                );

                if (!empty($thumbPath)) {
                    $user->profile_photo_path = $thumbPath;
                    $user->save();
                }
            }
        } else {
            // опционально обновлять yandex_email/phone/avatar можно, но НЕ обязательно
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        $request->session()->put('auth_provider', 'yandex');
        $request->session()->put('auth_provider_id', $yandexId);

        return redirect()->intended('/events');
    }
}
