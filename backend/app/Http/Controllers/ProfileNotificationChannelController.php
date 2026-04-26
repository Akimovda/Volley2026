<?php

namespace App\Http\Controllers;

use App\Models\ChannelBindRequest;
use App\Models\UserNotificationChannel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProfileNotificationChannelController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        $this->ensureCanManageChannels($user);

        $channels = UserNotificationChannel::query()
            ->where('user_id', (int) $user->id)
            ->orderByDesc('is_verified')
            ->orderBy('platform')
            ->orderBy('title')
            ->get();

        $bindRequests = ChannelBindRequest::query()
            ->where('user_id', (int) $user->id)
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return view('profile.notification-channels', [
            'channels'                   => $channels,
            'bindRequests'               => $bindRequests,
            'isPro'                      => $user->isOrganizerPro(),
            'notifyPlayerRegistrations'  => (bool) ($user->notify_player_registrations ?? false),
        ]);
    }

    public function createBind(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        $this->ensureCanManageChannels($user);

        $data = $request->validate([
            'platform' => ['required', 'string', 'in:telegram,vk,max'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $platform = (string) $data['platform'];
        $title = trim((string) ($data['title'] ?? ''));

        $token = Str::random(32);

        $bind = ChannelBindRequest::query()->create([
            'user_id' => (int) $user->id,
            'platform' => $platform,
            'token' => $token,
            'status' => 'pending',
            'expires_at' => Carbon::now()->addMinutes(30),
            'meta' => [
                'title' => $title !== '' ? $title : null,
                'created_from_profile' => true,
            ],
        ]);

        $instruction = $this->buildBindInstruction($platform, $token, $title);

        return redirect()
            ->route('profile.notification_channels')
            ->with('status', 'Ссылка для привязки канала создана.')
            ->with('bind_request_id', $bind->id)
            ->with('bind_instruction', $instruction);
    }

    public function destroy(Request $request, UserNotificationChannel $channel): RedirectResponse
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        $this->ensureCanManageChannels($user);

        if ((int) $channel->user_id !== (int) $user->id) {
            abort(403);
        }

        $channel->delete();

        return redirect()
            ->route('profile.notification_channels')
            ->with('status', 'Канал уведомлений удалён.');
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        $this->ensureCanManageChannels($user);

        $data = $request->validate([
            'notify_player_registrations' => ['nullable', 'boolean'],
        ]);

        $user->update([
            'notify_player_registrations' => (bool) ($data['notify_player_registrations'] ?? false),
        ]);

        return redirect()
            ->route('profile.notification_channels')
            ->with('status', 'Настройки уведомлений сохранены.');
    }

    private function ensureCanManageChannels($user): void
    {
        if (!$user) {
            abort(403);
        }

        $role = (string) ($user->role ?? 'user');

        if (!in_array($role, ['admin', 'organizer', 'staff'], true)) {
            abort(403);
        }
    }

    private function buildBindInstruction(string $platform, string $token, string $title = ''): array
    {
        $titleSuffix = $title !== '' ? " ({$title})" : '';
        $maxBotLink = rtrim((string) config('services.max.bot_link', ''), '/');

        return match ($platform) {
            'telegram' => [
                'platform' => 'telegram',
                'message' => 'Подключи бота в группу, супергруппу или канал Telegram.',
                'button_text' => 'Подключить в группу',
                'link' => 'https://t.me/' . config('services.telegram.bot_username') . '?startgroup=bind_' . $token,
                'command' => null,
                'instruction' => implode("\n", [
                    '1. Нажми кнопку «Подключить в группу» — Telegram предложит выбрать группу.',
                    '2. Для канала: добавь бота как администратора вручную, затем отправь /start bind_' . $token . ' в канал.',
                    '3. После добавления бот завершит привязку автоматически.',
                    '4. Если в группе есть темы (форум) — отправь /topic в нужной теме, чтобы анонсы шли туда.',
                    '5. Обнови страницу профиля.',
                ]),
                'title' => $titleSuffix,
            ],

            'vk' => [
                'platform' => 'vk',
                'message' => 'Привяжи бота к беседе или группе VK для отправки анонсов.',
                'button_text' => 'Открыть сообщество',
                'link' => (string) config('services.vk.bot_link', ''),
                'command' => 'bind_' . $token,
                'instruction' => implode("\n", [
                    '1. Добавь сообщество бота в нужную беседу VK.',
                    '2. Разреши сообществу доступ к сообщениям (если появится запрос).',
                    '3. Отправь в беседу: bind_' . $token,
                    '4. Бот подтвердит привязку. Обнови страницу профиля.',
                ]),
                'title' => $titleSuffix,
            ],


            'max' => [
                'platform' => 'max',
                'message' => 'Открой MAX-бота и выбери чат, куда нужно отправлять анонсы.',
                'button_text' => 'Открыть MAX',
                'link' => $maxBotLink !== '' ? ($maxBotLink . '?start=' . $token) : '',
                'command' => null,
                'instruction' => implode("\n", [
                    '1. Нажми кнопку «Открыть MAX».',
                    '2. В MAX откроется бот с уже переданным токеном привязки.',
                    '3. Бот покажет список чатов, где он участвует.',
                    '4. Выбери чат, куда должны приходить анонсы.',
                    '5. После подтверждения обнови страницу профиля.',
                    'Если у тебя несколько чатов, выбери нужный из списка.',
                ]),
                'title' => $titleSuffix,
            ],

            default => [
                'platform' => $platform,
                'message' => 'Ссылка для привязки создана.',
                'button_text' => 'Открыть',
                'link' => '',
                'command' => 'bind_' . $token,
                'instruction' => 'Передай токен боту: bind_' . $token,
                'title' => $titleSuffix,
            ],
        };
    }
}
