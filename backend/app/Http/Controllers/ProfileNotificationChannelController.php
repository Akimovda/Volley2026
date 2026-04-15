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
            'channels'     => $channels,
            'bindRequests' => $bindRequests,
            'isPro'        => $user->isOrganizerPro(),
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
                'message' => 'Открой Telegram и выбери группу или супергруппу, куда нужно подключить бота.',
                'button_text' => 'Подключить Telegram',
                'link' => 'https://t.me/' . config('services.telegram.bot_username') . '?startgroup=bind_' . $token,
                'command' => null,
                'instruction' => implode("\n", [
                    '1. Нажми кнопку «Подключить Telegram».',
                    '2. Telegram предложит выбрать группу или супергруппу.',
                    '3. Добавь бота в нужный чат.',
                    '4. После добавления бот завершит привязку автоматически.',
                    '5. После подтверждения обнови страницу профиля.',
                ]),
                'title' => $titleSuffix,
            ],

            'vk' => [
                'platform' => 'vk',
                'message' => 'Открой VK-бота и передай токен привязки.',
                'button_text' => 'Открыть VK',
                'link' => (string) config('services.vk.bot_link', ''),
                'command' => 'bind_' . $token,
                'instruction' => implode("\n", [
                    '1. Открой бота VK.',
                    '2. Добавь бота в нужный чат или открой личный диалог.',
                    '3. Отправь сообщение: bind_' . $token,
                    '4. После подтверждения обнови страницу профиля.',
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
