<?php

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
    public function redirect(Request $request)
    {
        $request->session()->put('oauth_provider', 'vk');
        $request->session()->put('oauth_intent', Auth::check() ? 'link' : 'login');

        // auth_provider НЕ трогаем
        return Socialite::driver('vkid')
            ->scopes(['email'])
            ->redirect();
    }

    public function callback(Request $request)
    {
        try {
            $vkUser = Socialite::driver('vkid')->user();
        } catch (\Laravel\Socialite\Two\InvalidStateException $e) {
            $vkUser = Socialite::driver('vkid')->stateless()->user();
        }

        $vkId    = (string) $vkUser->getId();
        $vkEmail = $vkUser->getEmail(); // может быть null
        $vkName  = $vkUser->getName() ?: "VK User #{$vkId}";
        $vkAvatar = method_exists($vkUser, 'getAvatar') ? $vkUser->getAvatar() : null;

        $intent = $request->session()->get('oauth_intent', Auth::check() ? 'link' : 'login');

        // ===== LINK =====
        if ($intent === 'link' && Auth::check()) {
            /** @var User $current */
            $current = Auth::user();

            $existsForOther = User::where('vk_id', $vkId)
                ->where('id', '!=', $current->id)
                ->exists();

            if ($existsForOther) {
                return redirect('/user/profile')->with('error', 'Этот VK уже привязан к другому аккаунту.');
            }

            $current->vk_id = $vkId;
            if (!empty($vkEmail)) $current->vk_email = $vkEmail;

            // avatar — только если нет profile_photo_path
            if (empty($current->profile_photo_path)) {
                $thumbPath = ProfilePhotoService::storeProviderAvatarIfMissing(
                    userId: (int) $current->id,
                    avatarUrl: $vkAvatar ?: null,
                    currentProfilePhotoPath: $current->profile_photo_path
                );
                if (!empty($thumbPath)) $current->profile_photo_path = $thumbPath;
            }

            $current->save();

            $request->session()->put('auth_provider', 'vk');
            $request->session()->put('auth_provider_id', $vkId);

            return redirect('/user/profile')->with('status', 'VK привязан ✅');
        }

        // ===== LOGIN =====
        $user = User::where('vk_id', $vkId)->first();

        if (!$user && !empty($vkEmail)) {
            $user = User::where('email', $vkEmail)->first();
        }

        if ($user) {
            if (empty($user->vk_id)) {
                $existsForOther = User::where('vk_id', $vkId)
                    ->where('id', '!=', $user->id)
                    ->exists();

                if ($existsForOther) {
                    abort(409, 'Этот VK уже привязан к другому аккаунту.');
                }

                $user->vk_id = $vkId;
            }

            if (!empty($vkEmail)) $user->vk_email = $vkEmail;

            // avatar — только если нет profile_photo_path
            if (empty($user->profile_photo_path)) {
                $thumbPath = ProfilePhotoService::storeProviderAvatarIfMissing(
                    userId: (int) $user->id,
                    avatarUrl: $vkAvatar ?: null,
                    currentProfilePhotoPath: $user->profile_photo_path
                );
                if (!empty($thumbPath)) $user->profile_photo_path = $thumbPath;
            }

            $user->save();
        }

        if (!$user) {
            // users.email NOT NULL => гарантируем
            if (!empty($vkEmail) && !User::where('email', $vkEmail)->exists()) {
                $safeEmail = $vkEmail;
            } else {
                $safeEmail = "vk_{$vkId}_" . now()->timestamp . "@vk.local";
            }

            $user = User::create([
                'name'     => $vkName,
                'email'    => $safeEmail,
                'password' => Hash::make(str()->random(32)),
                'vk_id'    => $vkId,
                'vk_email' => $vkEmail,
            ]);

            if (empty($user->profile_photo_path)) {
                $thumbPath = ProfilePhotoService::storeProviderAvatarIfMissing(
                    userId: (int) $user->id,
                    avatarUrl: $vkAvatar ?: null,
                    currentProfilePhotoPath: $user->profile_photo_path
                );
                if (!empty($thumbPath)) {
                    $user->profile_photo_path = $thumbPath;
                    $user->save();
                }
            }
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        $request->session()->put('auth_provider', 'vk');
        $request->session()->put('auth_provider_id', $vkId);

        return redirect()->intended('/events');
    }
}
