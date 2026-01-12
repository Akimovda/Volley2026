<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;

class VkAuthController extends Controller
{
    public function redirect(Request $request)
    {
        // ВАЖНО: auth_provider тут не трогаем
        $request->session()->put('oauth_provider', 'vk');
        $request->session()->put('oauth_intent', Auth::check() ? 'link' : 'login');

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

        $vkId = (string) $vkUser->getId();
        $vkEmail = $vkUser->getEmail(); // может быть null

        $intent = $request->session()->get('oauth_intent', Auth::check() ? 'link' : 'login');

        // =========================
        // MODE: LINK (привязка)
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

            $current->vk_id = $vkId;
            if ($vkEmail) $current->vk_email = $vkEmail;
            $current->save();

            $request->session()->put('auth_provider', 'vk');
            $request->session()->put('auth_provider_id', $vkId);

            return redirect('/user/profile')->with('status', 'VK привязан');
        }

        // =========================
        // MODE: LOGIN (вход/создание)
        // =========================
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

            if (!empty($vkEmail)) {
                $user->vk_email = $vkEmail;
            }

            $user->save();
        }

        if (!$user) {
            $name = $vkUser->getName() ?: "VK User #{$vkId}";

            if (!empty($vkEmail) && !User::where('email', $vkEmail)->exists()) {
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

        $request->session()->put('auth_provider', 'vk');
        $request->session()->put('auth_provider_id', $vkId);

        return redirect()->intended('/events');
    }
}
