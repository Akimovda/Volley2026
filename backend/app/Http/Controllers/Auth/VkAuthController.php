<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;

class VkAuthController extends Controller
{
    /**
     * Redirect to VK ID (OAuth 2.1 + PKCE)
     */
    public function redirect()
    {
        return Socialite::driver('vkid')
            ->scopes(['email'])
            ->redirect();
    }

    /**
     * VK ID callback
     */
    public function callback()
    {
        $vkUser = Socialite::driver('vkid')->user();

        $vkId    = (string) $vkUser->getId();
        $vkEmail = $vkUser->getEmail(); // может быть null

        /*
        |--------------------------------------------------------------------------
        | 3.1 Пользователь уже авторизован → привязка VK
        |--------------------------------------------------------------------------
        */
        if (Auth::check()) {
            /** @var User $current */
            $current = Auth::user();

            $existsForOther = User::where('vk_id', $vkId)
                ->where('id', '!=', $current->id)
                ->exists();

            if ($existsForOther) {
                abort(409, 'Этот VK уже привязан к другому аккаунту.');
            }

            $current->vk_id = $vkId;

            if ($vkEmail) {
                $current->vk_email = $vkEmail;

                // users.email меняем ТОЛЬКО если он пустой или служебный
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

            return redirect()->intended('/events');
        }

        /*
        |--------------------------------------------------------------------------
        | 3.2 Пользователь НЕ авторизован → логин / создание
        |--------------------------------------------------------------------------
        */

        // 1) Поиск по vk_id
        $user = User::where('vk_id', $vkId)->first();

        // 2) Если не нашли — по email (если VK его отдал)
        if (!$user && $vkEmail) {
            $user = User::where('email', $vkEmail)->first();
        }

        // 3) Если пользователь найден — аккуратно привязываем VK
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

        // 4) Если НЕ найден — создаём нового пользователя
        if (!$user) {
            $name = $vkUser->getName() ?: "VK User #{$vkId}";

            /*
             * users.email ДОЛЖЕН быть уникальным
             */
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

        Auth::login($user, true);

        /*
        |--------------------------------------------------------------------------
        | На первом этапе ВСЕГДА пускаем в сервис
        |--------------------------------------------------------------------------
        */
        return redirect()->intended('/events');
    }
}
