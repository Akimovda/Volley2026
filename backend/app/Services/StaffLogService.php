<?php
namespace App\Services;

use App\Models\StaffLog;
use App\Models\User;

class StaffLogService
{
    public function log(
        User   $staffUser,
        int    $organizerId,
        string $action,
        string $entityType = null,
        int    $entityId   = null,
        string $description = null,
        array  $meta       = []
    ): StaffLog {
        return StaffLog::create([
            'staff_user_id' => $staffUser->id,
            'organizer_id'  => $organizerId,
            'action'        => $action,
            'entity_type'   => $entityType,
            'entity_id'     => $entityId,
            'description'   => $description,
            'meta'          => $meta ?: null,
        ]);
    }

    public static function actionLabel(string $action): string
    {
        return match($action) {
            'create_event'         => '➕ Создал мероприятие',
            'edit_event'           => '✏️ Редактировал мероприятие',
            'cancel_event'         => '❌ Отменил мероприятие',
            'add_participant'      => '👤 Добавил участника',
            'remove_participant'   => '🗑 Удалил участника',
            'update_position'      => '🔄 Изменил позицию участника',
            'issue_subscription'   => '🎫 Выдал абонемент',
            'extend_subscription'  => '📅 Продлил абонемент',
            'cancel_registration'  => '🚫 Отменил запись',
            default                => $action,
        };
    }
}
