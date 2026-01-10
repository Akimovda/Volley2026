<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class TelegramAuthController extends Controller
{
public function redirect(Request $request)
{
    // Для UX на /user/profile
    $request->session()->put('auth_provider', 'telegram');

    // Telegram Login Widget — это не OAuth redirect, а страница с виджетом
    return view('auth.telegram-redirect', [
        'botName' => config('services.telegram.bot_name'),
        // Важно: сюда Telegram отправит query-параметры (id, hash, auth_date, username...)
        'authUrl' => route('auth.telegram.callback'),
    ]);
}
    public function callback(Request $request)
    {
        $data = $request->query();

        // ===== [SECURITY] Проверка подписи Telegram =====
        if (!$this->isValidTelegramAuth($data)) {
            abort(403, 'Invalid Telegram authentication');
        }

        // ===== [SECURITY] Защита от replay (например, сутки) =====
        if (isset($data['auth_date']) && (time() - (int) $data['auth_date']) > 86400) {
            abort(403, 'Telegram authentication expired');
        }

        // ===== [INPUT] Telegram user id =====
        $telegramId = (string) ($data['id'] ?? '');
        if ($telegramId === '') {
            abort(403, 'Telegram id missing');
        }

        // ===== [INPUT] Username (НЕ используем как name) =====
        $telegramUsername = $data['username'] ?? null;
        $telegramUsername = $telegramUsername ? ltrim($telegramUsername, '@') : null;

        /*
        |--------------------------------------------------------------------------
        | 1) Режим привязки: пользователь уже залогинен (email/пароль или другой провайдер)
        |--------------------------------------------------------------------------
        */
        if (Auth::check()) {
            /** @var \App\Models\User $currentUser */
            $currentUser = Auth::user();

            // Запрет: telegram_id уже привязан к ДРУГОМУ аккаунту
            $existsForOther = User::where('telegram_id', $telegramId)
                ->where('id', '!=', $currentUser->id)
                ->exists();

            if ($existsForOther) {
                abort(409, 'This Telegram account is already linked to another user.');
            }

            // Привязываем TG к текущему пользователю
            $currentUser->telegram_id = $telegramId;
            $currentUser->telegram_username = $telegramUsername;
            $currentUser->save();

            // ===== [SESSION] Запоминаем, что текущая сессия “TG” (для UX на /user/profile) =====
            $request->session()->put('auth_provider', 'telegram');
            $request->session()->put('auth_provider_id', (string) $telegramId);

            return redirect('/user/profile')->with('status', 'Telegram привязан');
        }

        /*
        |--------------------------------------------------------------------------
        | 2) Режим входа через Telegram (пользователь НЕ залогинен)
        |--------------------------------------------------------------------------
        */
        $fakeEmail = "tg_{$telegramId}@telegram.local";

        // Ищем по telegram_id ИЛИ по служебному email (fallback на старые записи)
        $user = User::where('telegram_id', $telegramId)
            ->orWhere('email', $fakeEmail)
            ->first();

        if (!$user) {
            // Создаём нового пользователя (name/email считаем служебными)
            $user = User::create([
                'name' => "TG User #{$telegramId}",   // служебное
                'email' => $fakeEmail,               // служебное, уникальное
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
            // Если нашли по email (старая запись) — проставим telegram_id (если пусто)
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

        // ===== [AUTH] Логин =====
        Auth::login($user, true);

        // ===== [SESSION] Запоминаем провайдера текущей сессии (НЕ дублируем session([...])) =====
        $request->session()->put('auth_provider', 'telegram');
        $request->session()->put('auth_provider_id', (string) $user->telegram_id);

        // Если профиль не заполнен — можно отправлять на заполнение (опционально)
        // $needsProfile = empty($user->last_name) || empty($user->first_name) || empty($user->phone);
        // if ($needsProfile) {
        //     return redirect('/profile/complete');
        // }

        return redirect()->intended('/events');
    }

    // ===== [SECURITY] Проверка подписи Telegram Login Widget =====
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
    }
}
