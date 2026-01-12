<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class YandexAuthController extends Controller
{
    /**
     * Редирект на Яндекс.
     *
     * /auth/yandex/redirect          -> режим LOGIN
     * /auth/yandex/redirect?link=1   -> режим LINK (привязать к текущему юзеру)
     */
    public function redirect(Request $request)
    {
        $isLink = $request->boolean('link');

        if ($isLink) {
            // LINK: привязка к текущему залогиненному пользователю
            if (!Auth::check()) {
                return redirect()->route('login')->with('error', 'Сначала войдите в аккаунт, чтобы привязать Яндекс.');
            }

            $request->session()->put('linking_user_id', Auth::id());
            $request->session()->put('linking_provider', 'yandex');

            // ВАЖНО: НЕ перезаписываем auth_provider в режиме привязки.
            // Иначе UI будет “прыгать” и ломать логику.
        } else {
            // LOGIN: обычный вход
            $request->session()->put('auth_provider', 'yandex');
        }

        return Socialite::driver('yandex')->redirect();
    }

    /**
     * Callback от Яндекса.
     */
    public function callback(Request $request)
    {
        // 1) Забираем юзера из Socialite
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

        // телефон у Яндекса часто не приходит — оставим как есть
        $phone = $raw['default_phone']['number'] ?? $raw['phone']['number'] ?? null;

        // 2) Проверяем: это LINK или LOGIN?
        $linkingUserId = $request->session()->pull('linking_user_id'); // pull = взять и удалить
        $linkingProvider = $request->session()->pull('linking_provider'); // ожидаем 'yandex'

        if (!empty($linkingUserId) && $linkingProvider === 'yandex') {
            // ===== LINK MODE: привязать yandex_id к текущему аккаунту =====
            $target = User::query()->find($linkingUserId);
            if (!$target) {
                return redirect()->route('login')->with('error', 'Не найден аккаунт для привязки Яндекса.');
            }

            // Если этот yandex_id уже у другого юзера — запрещаем
            $exists = User::query()
                ->where('yandex_id', $yandexId)
                ->where('id', '!=', $target->id)
                ->exists();

            if ($exists) {
                return redirect('/user/profile')->with('error', 'Этот Яндекс уже привязан к другому аккаунту.');
            }

            $target->yandex_id = $yandexId;

            // опциональные поля (если ты их добавил миграцией)
            if ($target->isFillable('yandex_avatar')) {
                $target->yandex_avatar = $avatar;
            }
            if ($target->isFillable('yandex_phone')) {
                $target->yandex_phone = $phone;
            }

            // если email пустой — можно аккуратно заполнить, но НЕ перетирать существующий
            if (empty($target->email) && !empty($email)) {
                $target->email = $email;
            }

            $target->save();

            return redirect('/user/profile')->with('status', 'Яндекс привязан ✅');
        }

        // ===== LOGIN MODE: найти/создать пользователя по yandex_id =====
        $user = User::query()->where('yandex_id', $yandexId)->first();

        if (!$user) {
    $user = new User();

    // имя
    if ($user->isFillable('name')) {
        $user->name = $name ?: 'Yandex user';
    }

    // email: Яндекс может не вернуть, а users.email у тебя NOT NULL
    // поэтому делаем безопасный email (уникальный)
    $safeEmail = null;

    if (!empty($email)) {
        // если email пришёл — используем его, но только если не занят другим
        $exists = User::where('email', $email)->exists();
        $safeEmail = $exists ? null : $email;
    }

    if (empty($safeEmail)) {
        // fallback: служебный email на основе yandex_id (уникальный)
        $safeEmail = "ya_{$yandexId}@yandex.local";
        // на всякий случай, если уже занят (редко, но возможно) — добавим timestamp
        if (User::where('email', $safeEmail)->exists()) {
            $safeEmail = "ya_{$yandexId}_" . now()->timestamp . "@yandex.local";
        }
    }

    if ($user->isFillable('email')) {
        $user->email = $safeEmail;
    }

    // пароль: чтобы Fortify/Jetstream не ругались, но пользователь логинится через OAuth
    if ($user->isFillable('password')) {
        $user->password = \Illuminate\Support\Facades\Hash::make(str()->random(32));
    }

    // обязательная привязка
    $user->yandex_id = $yandexId;

    // опциональные поля
    if ($user->isFillable('yandex_avatar')) {
        $user->yandex_avatar = $avatar;
    }
    if ($user->isFillable('yandex_phone')) {
        $user->yandex_phone = $phone;
    }

    $user->save();
}
        Auth::login($user, true);
        $request->session()->regenerate();

        // фиксируем “как вошли”
        $request->session()->put('auth_provider', 'yandex');

        // Куда вести после логина:
        return redirect()->intended('/events');
    }
}
