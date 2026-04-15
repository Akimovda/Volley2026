<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\UserNotificationChannel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PersonalBotController extends Controller
{
    /**
     * Верификация и сохранение персонального Telegram-бота.
     */
    public function storeTelegram(Request $request): RedirectResponse
    {
        if (!$request->user()->isOrganizerPro()) {
            return redirect()->back()
                ->with('error', 'Требуется подписка Организатор Pro.');
        }

        $data = $request->validate([
            'bot_token' => ['required', 'string', 'min:30'],
            'chat_id'   => ['required', 'string', 'min:3'],
            'title'     => ['nullable', 'string', 'max:255'],
        ]);

        $token  = trim($data['bot_token']);
        $chatId = trim($data['chat_id']);
        $title  = trim($data['title'] ?? '');

        // 1. Проверяем что токен валиден — getMe
        try {
            $meResp = Http::timeout(10)
                ->get("https://api.telegram.org/bot{$token}/getMe")
                ->json();
        } catch (\Throwable $e) {
            return back()->withErrors(['bot_token' => 'Не удалось подключиться к Telegram API: ' . $e->getMessage()]);
        }

        if (empty($meResp['ok'])) {
            return back()->withErrors(['bot_token' => 'Неверный токен бота. Проверьте токен от BotFather.']);
        }

        $botUsername = $meResp['result']['username'] ?? '';
        $botUserId   = $meResp['result']['id'] ?? null;

        // 2. Проверяем что бот является администратором указанного чата — getChatMember
        try {
            $memberResp = Http::timeout(10)
                ->get("https://api.telegram.org/bot{$token}/getChatMember", [
                    'chat_id' => $chatId,
                    'user_id' => $botUserId,
                ])
                ->json();
        } catch (\Throwable $e) {
            return back()->withErrors(['chat_id' => 'Не удалось проверить права бота в чате: ' . $e->getMessage()]);
        }

        if (empty($memberResp['ok'])) {
            $desc = $memberResp['description'] ?? 'неизвестная ошибка';
            return back()->withErrors(['chat_id' => "Бот не найден в чате: {$desc}. Убедитесь что бот добавлен в группу/канал."]); 
        }

        $status = $memberResp['result']['status'] ?? '';
        if (!in_array($status, ['administrator', 'creator'], true)) {
            return back()->withErrors(['chat_id' => "Бот @{$botUsername} есть в чате, но не является администратором (статус: {$status}). Выдайте боту права администратора."]);
        }

        // 3. Получаем название чата если title не задан
        if ($title === '') {
            try {
                $chatResp = Http::timeout(10)
                    ->get("https://api.telegram.org/bot{$token}/getChat", ['chat_id' => $chatId])
                    ->json();
                $title = $chatResp['result']['title']
                    ?? $chatResp['result']['username']
                    ?? "Telegram (@{$botUsername})";
            } catch (\Throwable) {
                $title = "Telegram (@{$botUsername})";
            }
        }

        // 4. Сохраняем канал
        UserNotificationChannel::query()->create([
            'user_id'              => (int) $request->user()->id,
            'platform'             => 'telegram',
            'title'                => $title,
            'chat_id'              => $chatId,
            'is_verified'          => true,
            'verified_at'          => now(),
            'bot_type'             => 'user',
            'user_bot_token'       => Crypt::encryptString($token),
            'user_bot_username'    => $botUsername,
            'user_bot_verified_at' => now(),
            'meta'                 => [
                'bot_user_id' => $botUserId,
                'bot_status'  => $status,
            ],
        ]);

        Log::info('PersonalBot: Telegram bot connected', [
            'user_id'      => $request->user()->id,
            'bot_username' => $botUsername,
            'chat_id'      => $chatId,
        ]);

        return redirect()
            ->route('profile.notification_channels')
            ->with('status', "✅ Бот @{$botUsername} успешно подключён к каналу «{$title}».");
    }

    /**
     * Верификация и сохранение персонального MAX-бота.
     */
    public function storeMax(Request $request): RedirectResponse
    {
        if (!$request->user()->isOrganizerPro()) {
            return redirect()->back()
                ->with('error', 'Требуется подписка Организатор Pro.');
        }

        $data = $request->validate([
            'bot_token' => ['required', 'string', 'min:10'],
            'chat_id'   => ['required', 'string', 'min:1'],
            'title'     => ['nullable', 'string', 'max:255'],
        ]);

        $token  = trim($data['bot_token']);
        $chatId = trim($data['chat_id']);
        $title  = trim($data['title'] ?? '');

        // 1. Проверяем токен — getMe в MAX API
        try {
            $meResp = Http::timeout(10)
                ->withHeaders(['Authorization' => $token])
                ->get('https://platform-api.max.ru/me')
                ->json();
        } catch (\Throwable $e) {
            return back()->withErrors(['bot_token' => 'Не удалось подключиться к MAX API: ' . $e->getMessage()]);
        }

        if (empty($meResp['user_id'])) {
            return back()->withErrors(['bot_token' => 'Неверный токен бота MAX. Проверьте токен.']);
        }

        $botName     = $meResp['name'] ?? 'MAX Bot';
        $botUsername = $meResp['username'] ?? '';

        // 2. Проверяем доступ к чату — пробуем getChat
        try {
            $chatResp = Http::timeout(10)
                ->withHeaders(['Authorization' => $token])
                ->get('https://platform-api.max.ru/chats/' . urlencode($chatId))
                ->json();
        } catch (\Throwable $e) {
            return back()->withErrors(['chat_id' => 'Не удалось проверить чат в MAX: ' . $e->getMessage()]);
        }

        if (!empty($chatResp['error'])) {
            return back()->withErrors(['chat_id' => 'Бот не имеет доступа к этому чату. Убедитесь что бот добавлен в чат и является администратором.']);
        }

        if ($title === '') {
            $title = $chatResp['title'] ?? $botName;
        }

        // 3. Сохраняем
        UserNotificationChannel::query()->create([
            'user_id'              => (int) $request->user()->id,
            'platform'             => 'max',
            'title'                => $title,
            'chat_id'              => $chatId,
            'is_verified'          => true,
            'verified_at'          => now(),
            'bot_type'             => 'user',
            'user_bot_token'       => Crypt::encryptString($token),
            'user_bot_username'    => $botUsername,
            'user_bot_verified_at' => now(),
            'meta'                 => [
                'bot_name' => $botName,
            ],
        ]);

        Log::info('PersonalBot: MAX bot connected', [
            'user_id'  => $request->user()->id,
            'bot_name' => $botName,
            'chat_id'  => $chatId,
        ]);

        return redirect()
            ->route('profile.notification_channels')
            ->with('status', "✅ Бот MAX «{$botName}» успешно подключён к чату «{$title}».");
    }

    /**
     * Обновить токен персонального бота (если скомпрометирован).
     */
    public function updateToken(Request $request, UserNotificationChannel $channel): RedirectResponse
    {
        if ((int) $channel->user_id !== (int) $request->user()->id) {
            abort(403);
        }

        if ($channel->bot_type !== 'user') {
            return back()->withErrors(['error' => 'Это системный канал, токен менять нельзя.']);
        }

        $data  = $request->validate(['bot_token' => ['required', 'string', 'min:10']]);
        $token = trim($data['bot_token']);

        $channel->update([
            'user_bot_token'       => Crypt::encryptString($token),
            'user_bot_verified_at' => now(),
        ]);

        return redirect()
            ->route('profile.notification_channels')
            ->with('status', '✅ Токен бота обновлён.');
    }
}
