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
            'token'      => ['required', 'string', 'min:10'],
            'group_slug' => ['required', 'string', 'max:150'],
        ]);

        $token = trim($request->input('token'));

        // Убрать vk.com/ префикс если пользователь вставил ссылку
        $groupSlug = trim($request->input('group_slug'));
        $groupSlug = preg_replace('#^https?://(www\.)?vk\.com/#i', '', $groupSlug);
        $groupSlug = trim($groupSlug, '/ ');

        // Шаг 1: валидировать токен через groups.getTokenPermissions
        // (не требует group_id, разработан для серверной проверки community-токенов)
        $permResp = Http::timeout(10)->get(self::API_BASE . 'groups.getTokenPermissions', [
            'access_token' => $token,
            'v'            => self::API_VER,
        ])->json();

        if ($this->isVkError($permResp)) {
            $errorStr = $this->vkErrorString($permResp);
            if ($this->isSecurityOrInvalidError($permResp)) {
                return back()
                    ->withInput(['group_slug' => $groupSlug])
                    ->with('error', '❌ Неверный токен или токен не является ключом доступа сообщества. Убедитесь, что скопировали его из раздела «Управление → Работа с API» своего сообщества.');
            }
            return back()
                ->withInput(['group_slug' => $groupSlug])
                ->with('error', '❌ Ошибка проверки токена VK: ' . $errorStr);
        }

        // Шаг 2: проверить права токена
        $permissions = $permResp['response']['permissions'] ?? [];
        $permNames   = array_column($permissions, 'name');

        if (!in_array('manage', $permNames, true)) {
            return back()
                ->withInput(['group_slug' => $groupSlug])
                ->with('error', '❌ Токен не имеет прав управления сообществом. При создании ключа отметьте «Разрешить приложению доступ к управлению сообществом».');
        }

        if (!in_array('wall', $permNames, true)) {
            return back()
                ->withInput(['group_slug' => $groupSlug])
                ->with('error', '❌ Токен не имеет прав на публикацию на стене. При создании ключа отметьте «Разрешить приложению доступ к стене сообщества».');
        }

        // Шаг 3: получить информацию о сообществе по явно указанному адресу
        $groupResp = Http::timeout(10)->get(self::API_BASE . 'groups.getById', [
            'access_token' => $token,
            'group_id'     => $groupSlug,
            'fields'       => 'screen_name',
            'v'            => self::API_VER,
        ])->json();

        if ($this->isVkError($groupResp)) {
            return back()
                ->withInput(['group_slug' => $groupSlug])
                ->with('error', '❌ Сообщество «' . e($groupSlug) . '» не найдено. Проверьте адрес — это должен быть адрес вашего сообщества ВКонтакте (например, club12345678 или msk_volley).');
        }

        $groups = $groupResp['response']['groups'] ?? $groupResp['response'] ?? [];
        if (empty($groups) || !is_array($groups)) {
            return back()
                ->withInput(['group_slug' => $groupSlug])
                ->with('error', '❌ Сообщество «' . e($groupSlug) . '» не найдено. Проверьте адрес сообщества.');
        }

        $group = $groups[0];
        $groupId    = (int) ($group['id'] ?? 0);
        $groupName  = (string) ($group['name'] ?? '');
        $screenName = (string) ($group['screen_name'] ?? $groupSlug);

        if (!$groupId) {
            return back()
                ->withInput(['group_slug' => $groupSlug])
                ->with('error', '❌ Не удалось определить ID сообщества. Попробуйте ещё раз.');
        }

        // Проверить: не привязано ли уже это сообщество
        $exists = UserNotificationChannel::where('user_id', Auth::id())
            ->where('platform', 'vk')
            ->where('chat_id', '-' . $groupId)
            ->exists();

        if ($exists) {
            return back()
                ->withInput(['group_slug' => $groupSlug])
                ->with('error', '⚠️ VK-сообщество «' . $groupName . '» уже привязано.');
        }

        // Шаг 4: сохранить канал
        UserNotificationChannel::create([
            'user_id'     => Auth::id(),
            'platform'    => 'vk',
            'chat_id'     => '-' . $groupId,
            'title'       => $groupName ?: $screenName,
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

    private function isVkError(mixed $resp): bool
    {
        return !is_array($resp) || isset($resp['error']);
    }

    private function isSecurityOrInvalidError(mixed $resp): bool
    {
        if (!is_array($resp)) {
            return true;
        }
        $err = $resp['error'] ?? null;
        if ($err === null) {
            return false;
        }
        // OAuth-формат: {"error":"invalid_request","error_description":"Security Error"}
        if (is_string($err) && ($err === 'invalid_request' || $err === 'access_denied')) {
            return true;
        }
        // Стандартный VK формат: error_code 5 (auth failed), 27 (community token required)
        if (is_array($err)) {
            $code = $err['error_code'] ?? 0;
            return in_array($code, [5, 27], true);
        }
        return false;
    }

    private function vkErrorString(mixed $resp): string
    {
        if (!is_array($resp)) {
            return 'нет ответа от VK';
        }
        $err = $resp['error'] ?? null;
        if (is_string($err)) {
            return $resp['error_description'] ?? $err;
        }
        if (is_array($err)) {
            return $err['error_msg'] ?? ('код ' . ($err['error_code'] ?? '?'));
        }
        return 'неизвестная ошибка';
    }
}
