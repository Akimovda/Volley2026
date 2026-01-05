<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class TelegramAuthController extends Controller
{
    public function callback(Request $request)
    {
        $data = $request->query();

        if (!$this->isValidTelegramAuth($data)) {
            abort(403, 'Invalid Telegram authentication');
        }

        // защита от replay (например, сутки)
        if (isset($data['auth_date']) && (time() - (int)$data['auth_date']) > 86400) {
            abort(403, 'Telegram authentication expired');
        }

        $telegramId = (string) ($data['id'] ?? '');
        if ($telegramId === '') {
            abort(403, 'Telegram id missing');
        }

        // Telegram username НЕ используем как имя пользователя.
        // Храним только как telegram_username.
        $telegramUsername = $data['username'] ?? null;
        $telegramUsername = $telegramUsername ? ltrim($telegramUsername, '@') : null;

        // 1) Режим привязки: если пользователь уже залогинен (email/пароль)
        if (Auth::check()) {
            /** @var \App\Models\User $currentUser */
            $currentUser = Auth::user();

            // Запрет: telegram_id уже привязан к другому аккаунту
            $existsForOther = User::where('telegram_id', $telegramId)
                ->where('id', '!=', $currentUser->id)
                ->exists();

            if ($existsForOther) {
                abort(409, 'This Telegram account is already linked to another user.');
            }

            $currentUser->telegram_id = $telegramId;
            $currentUser->telegram_username = $telegramUsername;
            $currentUser->save();

            return redirect('/user/profile')->with('status', 'Telegram привязан');
        }

        // 2) Режим входа через Telegram (пользователь не залогинен)
        $fakeEmail = "tg_{$telegramId}@telegram.local";

        // Ищем по telegram_id ИЛИ по служебному email (fallback на старые записи)
        $user = User::where('telegram_id', $telegramId)
            ->orWhere('email', $fakeEmail)
            ->first();

        if (!$user) {
            // ВАЖНО: не использовать telegram username как name
            $user = User::create([
                'name' => "TG User #{$telegramId}",   // служебное
                'email' => $fakeEmail,                // служебное, уникальное
                'password' => Hash::make(str()->random(32)),
                'telegram_id' => $telegramId,
                'telegram_username' => $telegramUsername,

                // поля профиля пользователь заполнит позже (если колонки уже добавлены)
                'last_name' => null,
                'first_name' => null,
                'patronymic' => null,
                'phone' => null,
                'phone_verified_at' => null,
            ]);
        } else {
            // Если нашли по email (старая запись) — проставим telegram_id
            if (empty($user->telegram_id)) {
                $existsForOther = User::where('telegram_id', $telegramId)
                    ->where('id', '!=', $user->id)
                    ->exists();

                if ($existsForOther) {
                    abort(409, 'This Telegram account is already linked to another user.');
                }

                $user->telegram_id = $telegramId;
            }

            // Обновим telegram_username при необходимости
            if ($telegramUsername !== $user->telegram_username) {
                $user->telegram_username = $telegramUsername;
            }

            $user->save();
        }

               Auth::login($user, true);

        // Если профиль не заполнен — отправим на заполнение (страницу сделаем позже)
    //    $needsProfile = empty($user->last_name) || empty($user->first_name) || empty($user->phone);

    //    if ($needsProfile) {
    //        return redirect('/profile/complete');
    //    }

        return redirect()->intended('/events');
    } // ✅ вот это закрывает callback()

    private function isValidTelegramAuth(array $data): bool
    {
        if (empty($data['hash'])) {
            return false;
        }

        $botToken = config('services.telegram.bot_token');
        if (!$botToken) {
            return false;
        }

        $checkHash = $data['hash'];
        unset($data['hash']);

        ksort($data);

        $dataCheckString = collect($data)
            ->map(fn ($v, $k) => $k . '=' . $v)
            ->implode("\n");

        $secretKey = hash('sha256', $botToken, true);
        $hash = hash_hmac('sha256', $dataCheckString, $secretKey);

        return hash_equals($hash, $checkHash);
    } // ✅ закрывает isValidTelegramAuth()
} // ✅ закрывает класс TelegramAuthController

