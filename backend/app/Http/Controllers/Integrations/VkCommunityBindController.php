<?php

declare(strict_types=1);

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Models\UserNotificationChannel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class VkCommunityBindController extends Controller
{
    private const API_BASE = 'https://api.vk.com/method/';
    private const API_VER  = '5.199';

    public function bind(Request $request)
    {
        $request->validate([
            'token' => ['required', 'string', 'min:10'],
        ]);

        $token = trim($request->input('token'));

        // Шаг 1: проверить токен и получить данные сообщества
        $groupResp = Http::timeout(10)->get(self::API_BASE . 'groups.getById', [
            'access_token' => $token,
            'v'            => self::API_VER,
        ])->json();

        if (isset($groupResp['error'])) {
            $code = $groupResp['error']['error_code'] ?? 0;
            if ($code === 5 || $code === 27) {
                return back()->with('error', '❌ Неверный токен. Проверьте что вы скопировали его полностью.');
            }
            return back()->with('error', '❌ Ошибка VK API: ' . ($groupResp['error']['error_msg'] ?? 'неизвестная ошибка'));
        }

        $groups = $groupResp['response']['groups'] ?? $groupResp['response'] ?? [];
        if (empty($groups)) {
            return back()->with('error', '❌ Не удалось получить данные сообщества. Убедитесь что токен принадлежит сообществу.');
        }

        $group = $groups[0];
        $groupId    = (int) ($group['id'] ?? 0);
        $groupName  = $group['name'] ?? '';
        $screenName = $group['screen_name'] ?? '';

        if (!$groupId) {
            return back()->with('error', '❌ Не удалось определить ID сообщества.');
        }

        // Шаг 2: проверить права токена
        $permResp = Http::timeout(10)->get(self::API_BASE . 'groups.getTokenPermissions', [
            'access_token' => $token,
            'v'            => self::API_VER,
        ])->json();

        if (isset($permResp['error'])) {
            return back()->with('error', '❌ Не удалось проверить права токена. Попробуйте создать токен заново.');
        }

        $permissions = $permResp['response']['permissions'] ?? [];
        $permNames   = array_column($permissions, 'name');

        if (!in_array('manage', $permNames, true)) {
            return back()->with('error', '❌ Токен не имеет прав управления сообществом. При создании ключа отметьте «Разрешить приложению доступ к управлению сообществом».');
        }

        if (!in_array('wall', $permNames, true)) {
            return back()->with('error', '❌ Токен не имеет прав на публикацию на стене. При создании ключа отметьте «Разрешить приложению доступ к стене сообщества».');
        }

        // Шаг 3: сохранить канал
        UserNotificationChannel::create([
            'user_id'     => Auth::id(),
            'platform'    => 'vk',
            'chat_id'     => '-' . $groupId,
            'title'       => $groupName ?: ('VK: ' . $screenName),
            'is_verified' => true,
            'verified_at' => now(),
            'bot_type'    => 'system',
            'meta'        => [
                'kind'              => 'vk_community',
                'access_token'      => encrypt($token),
                'group_id'          => $groupId,
                'group_name'        => $groupName,
                'group_screen_name' => $screenName,
            ],
        ]);

        return redirect()->route('profile.notification_channels')
            ->with('status', '✅ VK-сообщество «' . $groupName . '» успешно привязано!');
    }
}
