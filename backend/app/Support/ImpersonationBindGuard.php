<?php

namespace App\Support;

use App\Models\User;

/**
 * Защита привязки личных мессенджеров при работе админа под чужим аккаунтом (impersonate).
 * Админ помогает пользователям — создавать ссылку привязки под чужим аккаунтом можно (её откроет владелец аккаунта),
 * но завершить такую привязку со своего собственного Telegram/VK/MAX нельзя: иначе все личные уведомления
 * чужого аккаунта (записи, анонсы, организаторские) уходят админу.
 */
class ImpersonationBindGuard
{
    /** id админа, если сейчас идёт impersonate (иначе null). */
    public static function adminId(): ?int
    {
        $id = session('impersonator_id');
        return $id ? (int) $id : null;
    }

    /**
     * @param object $binding строка *_bindings (с created_by_admin_id)
     * @param string $platform telegram|vk|max
     * @param array  $incomingIds идентификаторы мессенджера, с которых пришло завершение (chat_id, user_id, peer_id)
     */
    public static function assertNotAdminIdentity(object $binding, string $platform, array $incomingIds): void
    {
        $adminId = (int) ($binding->created_by_admin_id ?? 0);
        if ($adminId <= 0) {
            return;
        }
        $admin = User::query()->find($adminId);
        if (!$admin) {
            return;
        }

        $own = match ($platform) {
            'telegram' => [$admin->telegram_id, $admin->telegram_user_id, $admin->telegram_notify_chat_id],
            'vk'       => [$admin->vk_id, $admin->vk_notify_user_id],
            'max'      => [$admin->max_chat_id],
            default    => [],
        };
        $own = array_values(array_filter(array_map(fn ($v) => trim((string) $v), $own), fn ($v) => $v !== ''));

        foreach ($incomingIds as $id) {
            $id = trim((string) $id);
            if ($id !== '' && in_array($id, $own, true)) {
                abort(422, 'Нельзя привязать мессенджер администратора к чужому аккаунту: отправьте ссылку владельцу аккаунта — пусть откроет её сам.');
            }
        }
    }
}
