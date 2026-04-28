<?php

declare(strict_types=1);

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Models\UserNotificationChannel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class VkCommunityBindController extends Controller
{
    private const OAUTH_URL   = 'https://oauth.vk.com/authorize';
    private const TOKEN_URL   = 'https://oauth.vk.com/access_token';
    private const GROUPS_URL  = 'https://api.vk.com/method/groups.get';
    private const API_VER     = '5.199';

    // Минимальные scope: управление стеной сообщества
    private const SCOPE = 'wall,groups,offline';

    public function redirect(Request $request)
    {
        $request->validate(['title' => ['required', 'string', 'max:128']]);

        $title = $request->input('title');
        $state = Str::random(32);

        session([
            'vk_wall_oauth_state' => $state,
            'vk_wall_channel_title' => $title,
        ]);

        $params = http_build_query([
            'client_id'     => config('services.vk_wall.client_id'),
            'redirect_uri'  => config('services.vk_wall.redirect'),
            'display'       => 'page',
            'scope'         => self::SCOPE,
            'response_type' => 'code',
            'v'             => self::API_VER,
            'state'         => $state,
        ]);

        return redirect(self::OAUTH_URL . '?' . $params);
    }

    public function callback(Request $request)
    {
        if ($request->input('state') !== session('vk_wall_oauth_state')) {
            return redirect()->route('profile.notification_channels')
                ->with('error', '❌ Ошибка авторизации VK: неверный state.');
        }

        if ($request->has('error')) {
            return redirect()->route('profile.notification_channels')
                ->with('error', '❌ VK отказал в доступе: ' . $request->input('error_description', 'unknown'));
        }

        $code = $request->input('code');

        $tokenResp = Http::timeout(15)->get(self::TOKEN_URL, [
            'client_id'     => config('services.vk_wall.client_id'),
            'client_secret' => config('services.vk_wall.client_secret'),
            'redirect_uri'  => config('services.vk_wall.redirect'),
            'code'          => $code,
        ])->json();

        if (isset($tokenResp['error'])) {
            return redirect()->route('profile.notification_channels')
                ->with('error', '❌ Ошибка получения токена VK: ' . ($tokenResp['error_description'] ?? $tokenResp['error']));
        }

        $accessToken = $tokenResp['access_token'] ?? null;

        if (!$accessToken) {
            return redirect()->route('profile.notification_channels')
                ->with('error', '❌ VK не вернул access_token.');
        }

        // Получаем список сообществ, где пользователь — администратор
        $groupsResp = Http::timeout(15)->get(self::GROUPS_URL, [
            'access_token' => $accessToken,
            'filter'       => 'admin',
            'extended'     => 1,
            'fields'       => 'name,photo_50',
            'count'        => 100,
            'v'            => self::API_VER,
        ])->json();

        if (isset($groupsResp['error'])) {
            return redirect()->route('profile.notification_channels')
                ->with('error', '❌ Не удалось получить список сообществ VK.');
        }

        $groups = $groupsResp['response']['items'] ?? [];

        if (empty($groups)) {
            return redirect()->route('profile.notification_channels')
                ->with('error', '❌ Нет сообществ ВКонтакте, где вы являетесь администратором.');
        }

        session([
            'vk_wall_access_token'   => encrypt($accessToken),
            'vk_wall_groups'         => $groups,
        ]);

        $title = session('vk_wall_channel_title', 'VK-сообщество');

        return view('integrations.vk_community_select', compact('groups', 'title'));
    }

    public function selectGroup(Request $request)
    {
        $request->validate([
            'group_id' => ['required', 'integer'],
            'title'    => ['required', 'string', 'max:128'],
        ]);

        $groupId       = (int) $request->input('group_id');
        $title         = $request->input('title');
        $encryptedToken = session('vk_wall_access_token');
        $groups         = session('vk_wall_groups', []);

        if (!$encryptedToken) {
            return redirect()->route('profile.notification_channels')
                ->with('error', '❌ Сессия истекла. Повторите привязку.');
        }

        // Ищем имя группы
        $group = collect($groups)->firstWhere('id', $groupId);
        if (!$group) {
            return redirect()->route('profile.notification_channels')
                ->with('error', '❌ Сообщество не найдено.');
        }

        // Создаём канал (owner_id — отрицательный для сообщества)
        UserNotificationChannel::create([
            'user_id'     => Auth::id(),
            'platform'    => 'vk',
            'chat_id'     => (string) (-$groupId),
            'title'       => $title,
            'is_verified' => true,
            'verified_at' => now(),
            'bot_type'    => 'system',
            'meta'        => [
                'kind'         => 'vk_wall',
                'group_id'     => $groupId,
                'group_name'   => $group['name'] ?? '',
                'access_token' => $encryptedToken,
            ],
        ]);

        session()->forget(['vk_wall_access_token', 'vk_wall_groups', 'vk_wall_oauth_state', 'vk_wall_channel_title']);

        return redirect()->route('profile.notification_channels')
            ->with('status', '✅ VK-сообщество «' . ($group['name'] ?? '') . '» успешно подключено!');
    }
}
