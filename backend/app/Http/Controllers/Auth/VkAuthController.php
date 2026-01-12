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

class VkAuthController extends Controller
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

        $request->session()->put('oauth_provider', 'vk');
        $request->session()->put('oauth_intent', ($isLink && Auth::check()) ? 'link' : 'login');

        if ($isLink && !Auth::check()) {
            return redirect()->route('login')->with('error', 'Сначала войдите в аккаунт, чтобы привязать VK.');
        }

        return Socialite::driver('vkid')
            ->scopes(['email'])
            ->redirect();
    }

    /**
     * ------------------------------------------------------------
     * CALLBACK (единый login/link)
     * ------------------------------------------------------------
     */
    public function callback(Request $request)
    {
        try {
            $vkUser = Socialite::driver('vkid')->user();
        } catch (\Laravel\Socialite\Two\InvalidStateException $e) {
            $vkUser = Socialite::driver('vkid')->stateless()->user();
        }

        $vkId    = (string) $vkUser->getId();
        $vkEmail = $vkUser->getEmail(); // может быть null
        $name    = $vkUser->getName();
        $avatar  = $vkUser->getAvatar();

        $intent = (string) $request->session()->get('oauth_intent', Auth::check() ? 'link' : 'login');

        // =========================
        // MODE: LINK
        // =========================
        if ($intent === 'link') {
            if (!Auth::check()) {
                return redirect()->route('login')->with('error', 'Сессия истекла. Войдите и повторите привязку VK.');
            }

            /** @var User $current */
            $current = Auth::user();

            $existsForOther = User::query()
                ->where('vk_id', $vkId)
                ->where('id', '!=', $current->id)
                ->exists();

            if ($existsForOther) {
                return redirect('/user/profile')->with('error', 'Этот VK уже привязан к другому аккаунту.');
            }

            $current->vk_id = $vkId;
            if (!empty($vkEmail)) {
                $current->vk_email = $vkEmail;
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

            // фиксируем факт успешной привязки
            $request->session()->put('auth_provider', 'vk');
            $request->session()->put('auth_provider_id', $vkId);

            return redirect('/user/profile')->with('status', 'VK привязан ✅');
        }

        // =========================
        // MODE: LOGIN
        // =========================
        $user = User::query()->where('vk_id', $vkId)->first();

        // fallback по email (если пришёл)
        if (!$user && !empty($vkEmail)) {
            $user = User::query()->where('email', $vkEmail)->first();
        }

        if ($user) {
            // дозапишем vk_id если найден по email
            if (empty($user->vk_id)) {
                $existsForOther = User::query()
                    ->where('vk_id', $vkId)
                    ->where('id', '!=', $user->id)
                    ->exists();

                if ($existsForOther) {
                    abort(409, 'Этот VK уже привязан к другому аккаунту.');
                }

                $user->vk_id = $vkId;
            }

            if (!empty($vkEmail)) {
                $user->vk_email = $vkEmail;
            }

            // avatar only if missing
            $thumbPath = ProfilePhotoService::storeProviderAvatarIfMissing(
                userId: (int) $user->id,
                avatarUrl: $avatar ?: null,
                currentProfilePhotoPath: $user->profile_photo_path ?? null,
            );
            if (!empty($thumbPath)) {
                $user->profile_photo_path = $thumbPath;
            }

            $user->save();
        }

        if (!$user) {
            $safeName = $name ?: "VK User #{$vkId}";

            // email NOT NULL => делаем безопасный
            if (!empty($vkEmail) && !User::query()->where('email', $vkEmail)->exists()) {
                $safeEmail = $vkEmail;
            } else {
                $safeEmail = "vk_{$vkId}_" . now()->timestamp . "@vk.local";
            }

            $user = User::query()->create([
                'name'     => $safeName,
                'email'    => $safeEmail,
                'password' => Hash::make(str()->random(32)),
                'vk_id'    => $vkId,
                'vk_email' => $vkEmail,
            ]);

            // avatar only if missing (после create id уже есть)
            $thumbPath = ProfilePhotoService::storeProviderAvatarIfMissing(
                userId: (int) $user->id,
                avatarUrl: $avatar ?: null,
                currentProfilePhotoPath: $user->profile_photo_path ?? null,
            );
            if (!empty($thumbPath)) {
                $user->profile_photo_path = $thumbPath;
                $user->save();
            }
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        $request->session()->put('auth_provider', 'vk');
        $request->session()->put('auth_provider_id', $vkId);

        return redirect()->intended('/events');
    }
}
