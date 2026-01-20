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
    /**
     * ЕДИНООБРАЗНЫЙ redirect():
     * - записываем oauth_provider / oauth_intent
     * - auth_provider НЕ трогаем
     *
     * Теперь вместо Socialite redirect показываем страницу с VKID-виджетом.
     */
    public function redirect(Request $request)
    {
        $isLink = $request->boolean('link');

        if ($isLink && !Auth::check()) {
            return redirect()->route('login')->with('error', 'Сначала войдите, чтобы привязать VK.');
        }

        $request->session()->put('oauth_provider', 'vk');
        $request->session()->put('oauth_intent', ($isLink && Auth::check()) ? 'link' : 'login');

        return redirect()->route('auth.vk.widget');
    }

    /**
     * Страница с VKID OneTap.
     */
    public function widget(Request $request)
    {
        if (!$request->session()->has('oauth_intent')) {
            $request->session()->put('oauth_provider', 'vk');
            $request->session()->put('oauth_intent', Auth::check() ? 'link' : 'login');
        }

        // Берём app id из services.vkid.client_id
        $vkAppId = (string) config('services.vkid.client_id');

        // На виджете используем callback-mode, значит VK вернёт code/device_id на redirectUrl
        $vkRedirectUrl = route('auth.vk.callback', [], true);

        return view('auth.vk-widget', [
            'vkAppId' => $vkAppId,
            'vkRedirectUrl' => $vkRedirectUrl,
        ]);
    }

    public function callback(Request $request)
    {
        /**
         * ВАЖНО:
         * VKID OneTap (Callback) приходит БЕЗ state -> обычный Socialite->user() падает.
         * Поэтому для виджета используем stateless().
         */
        try {
            $vkUser = Socialite::driver('vkid')
                ->stateless()
                ->scopes(['email'])
                ->user();
        } catch (\Throwable $e) {
            // На всякий случай: если что-то пошло не так — вернём на логин
            return redirect()->route('login')->with('error', 'VK auth: не удалось получить данные пользователя.');
        }

        $vkId    = (string) $vkUser->getId();
        $vkEmail = $vkUser->getEmail(); // может быть null
        $name    = $vkUser->getName();
        $avatar  = $vkUser->getAvatar();

        $intent = (string) $request->session()->pull('oauth_intent', Auth::check() ? 'link' : 'login');
        $request->session()->forget('oauth_provider');

        // =========================
        // LINK
        // =========================
        if ($intent === 'link' && Auth::check()) {
            /** @var User $current */
            $current = Auth::user();

            $existsForOther = User::where('vk_id', $vkId)
                ->where('id', '!=', $current->id)
                ->exists();

            if ($existsForOther) {
                return redirect('/user/profile')->with('error', 'Этот VK уже привязан к другому аккаунту.');
            }

            if ($current->isFillable('vk_id')) {
                $current->vk_id = $vkId;
            }

            if ($current->isFillable('vk_email') && !empty($vkEmail) && empty($current->vk_email)) {
                $current->vk_email = $vkEmail;
            }

            // avatar -> только если пусто
            $baseName = ProfilePhotoService::storeProviderAvatarBasenameIfMissing(
                userId: (int) $current->id,
                avatarUrl: $avatar,
                currentProfilePhotoPath: $current->profile_photo_path ?? null
            );

            if (!empty($baseName) && $current->isFillable('profile_photo_path')) {
                $current->profile_photo_path = $baseName;
            }

            $current->save();

            $request->session()->put('auth_provider', 'vk');
            $request->session()->put('auth_provider_id', $vkId);

            return redirect('/user/profile')->with('status', 'VK привязан ✅');
        }

        // =========================
        // LOGIN
        // =========================
        $user = User::where('vk_id', $vkId)->first();

        // (опционально) если email пришёл и есть совпадение — можно привязать к существующему
        if (!$user && !empty($vkEmail)) {
            $user = User::where('email', $vkEmail)->first();

            if ($user) {
                $existsForOther = User::where('vk_id', $vkId)->where('id', '!=', $user->id)->exists();
                if ($existsForOther) {
                    abort(409, 'Этот VK уже привязан к другому аккаунту.');
                }

                if ($user->isFillable('vk_id') && empty($user->vk_id)) $user->vk_id = $vkId;
                if ($user->isFillable('vk_email') && !empty($vkEmail) && empty($user->vk_email)) $user->vk_email = $vkEmail;

                $user->save();
            }
        }

        if (!$user) {
            $displayName = $name ?: "VK User #{$vkId}";

            // users.email NOT NULL -> безопасный email
            $safeEmail = null;
            if (!empty($vkEmail) && !User::where('email', $vkEmail)->exists()) {
                $safeEmail = $vkEmail;
            }

            if (empty($safeEmail)) {
                $safeEmail = "vk_{$vkId}@vk.local";
                if (User::where('email', $safeEmail)->exists()) {
                    $safeEmail = "vk_{$vkId}_" . now()->timestamp . "@vk.local";
                }
            }

            $user = new User();
            if ($user->isFillable('name')) $user->name = $displayName;
            if ($user->isFillable('email')) $user->email = $safeEmail;
            if ($user->isFillable('password')) $user->password = Hash::make(str()->random(32));

            if ($user->isFillable('vk_id')) $user->vk_id = $vkId;
            if ($user->isFillable('vk_email') && !empty($vkEmail)) $user->vk_email = $vkEmail;

            $user->save();
        }

        // avatar -> только если пусто
        $baseName = ProfilePhotoService::storeProviderAvatarBasenameIfMissing(
            userId: (int) $user->id,
            avatarUrl: $avatar,
            currentProfilePhotoPath: $user->profile_photo_path ?? null
        );

        if (!empty($baseName) && $user->isFillable('profile_photo_path')) {
            $user->profile_photo_path = $baseName;
            $user->save();
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        $request->session()->put('auth_provider', 'vk');
        $request->session()->put('auth_provider_id', $vkId);

        return redirect()->intended('/events');
    }
}
