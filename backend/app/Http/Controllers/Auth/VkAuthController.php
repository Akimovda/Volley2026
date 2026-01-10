<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;

class VkAuthController extends Controller
{
    /**
     * ============================================================
     * [VK-REDIRECT] Редирект на VK ID (OAuth 2.1 + PKCE)
     * ============================================================
     */
    public function redirect(Request $request)
    {
        // [STEP-4] запоминаем, что стартовали OAuth именно с VK
        // (полезно для UX и для страницы профиля)
        $request->session()->put('auth_provider', 'vk');

        return Socialite::driver('vkid')
            ->scopes(['email'])
            ->redirect();
    }

    /**
     * ============================================================
     * [VK-CALLBACK] Возврат с VK ID
     * ============================================================
     */
    public function callback(Request $request)
    {
        /**
         * ------------------------------------------------------------
         * [VK-OAUTH] Получаем пользователя из VK
         * ------------------------------------------------------------
         *
         * В production иногда может прилетать InvalidStateException
         * (особенно если были проблемы с cookies/сессией/прокси).
         * Тогда используем stateless() как fallback.
         */
        try {
            $vkUser = Socialite::driver('vkid')->user();
        } catch (\Laravel\Socialite\Two\InvalidStateException $e) {
            $vkUser = Socialite::driver('vkid')->stateless()->user();
        }

        $vkId    = (string) $vkUser->getId();
        $vkEmail = $vkUser->getEmail(); // может быть null

        /**
         * ------------------------------------------------------------
         * [MODE-LINK] Пользователь уже авторизован → привязка VK к текущему аккаунту
         * ------------------------------------------------------------
         */
        if (Auth::check()) {
            /** @var User $current */
            $current = Auth::user();

            // [GUARD] нельзя привязать vk_id, который уже у другого пользователя
            $existsForOther = User::where('vk_id', $vkId)
                ->where('id', '!=', $current->id)
                ->exists();

            if ($existsForOther) {
                abort(409, 'Этот VK уже привязан к другому аккаунту.');
            }

            // [LINK] сохраняем vk_id и vk_email
            $current->vk_id = $vkId;

            if ($vkEmail) {
                $current->vk_email = $vkEmail;

                // [EMAIL] users.email меняем ТОЛЬКО если он пустой или служебный
                if (
                    empty($current->email) ||
                    str_ends_with($current->email, '@telegram.local') ||
                    str_ends_with($current->email, '@vk.local')
                ) {
                    $busy = User::where('email', $vkEmail)
                        ->where('id', '!=', $current->id)
                        ->exists();

                    if (!$busy) {
                        $current->email = $vkEmail;
                    }
                }
            }

            $current->save();

            // [STEP-4] фиксируем провайдера текущей сессии (для UX в /user/profile)
            $request->session()->put('auth_provider', 'vk');
            $request->session()->put('auth_provider_id', $vkId);

            return redirect()->intended('/events');
        }

        /**
         * ------------------------------------------------------------
         * [MODE-LOGIN] Пользователь НЕ авторизован → логин / создание
         * ------------------------------------------------------------
         */

        // [FIND-1] поиск по vk_id
        $user = User::where('vk_id', $vkId)->first();

        // [FIND-2] если не нашли — по email (если VK его отдал)
        if (!$user && $vkEmail) {
            $user = User::where('email', $vkEmail)->first();
        }

        // [BIND] если нашли пользователя — аккуратно привяжем VK
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

            if ($vkEmail) {
                $user->vk_email = $vkEmail;
            }

            $user->save();
        }

        // [CREATE] если НЕ нашли — создаём нового пользователя
        if (!$user) {
            $name = $vkUser->getName() ?: "VK User #{$vkId}";

            // users.email должен быть уникальным
            if ($vkEmail && !User::where('email', $vkEmail)->exists()) {
                $safeEmail = $vkEmail;
            } else {
                $safeEmail = "vk_{$vkId}_" . now()->timestamp . "@vk.local";
            }

            $user = User::create([
                'name'     => $name,
                'email'    => $safeEmail,
                'password' => Hash::make(str()->random(32)),
                'vk_id'    => $vkId,
                'vk_email' => $vkEmail,
            ]);
        }

        // [LOGIN] авторизуем
        Auth::login($user, true);

        // [STEP-4] фиксируем провайдера текущей сессии (ВАЖНО: без дублей session([...])!)
        $request->session()->put('auth_provider', 'vk');
        $request->session()->put('auth_provider_id', $vkId);

        return redirect()->intended('/events');
    }
}
