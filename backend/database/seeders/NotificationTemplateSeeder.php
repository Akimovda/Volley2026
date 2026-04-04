<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'code' => 'registration_created',
                'name' => 'Запись на мероприятие',
                'channel' => null,
                'title_template' => 'Вы записаны на {event_title}',
                'body_template' => "Дата: {event_date} {event_time}\nАдрес: {event_address}\n\nОткрыть мероприятие:\n{event_url}",
                'image_url' => null,
                'button_text' => 'Открыть мероприятие',
                'button_url_template' => '{event_url}',
                'is_active' => true,
            ],
            [
                'code' => 'registration_cancelled',
                'name' => 'Отмена своей записи',
                'channel' => null,
                'title_template' => 'Запись отменена',
                'body_template' => "Вы отменили запись на {event_title}.\n\nДата: {event_date} {event_time}\nАдрес: {event_address}\n{event_url}",
                'image_url' => null,
                'button_text' => 'Открыть мероприятие',
                'button_url_template' => '{event_url}',
                'is_active' => true,
            ],
            [
                'code' => 'registration_cancelled_by_organizer',
                'name' => 'Организатор отменил запись',
                'channel' => null,
                'title_template' => 'Ваша запись отменена',
                'body_template' => "Организатор отменил вашу запись на {event_title}.\n\nДата: {event_date} {event_time}\nАдрес: {event_address}\n{event_url}",
                'image_url' => null,
                'button_text' => 'Открыть мероприятие',
                'button_url_template' => '{event_url}',
                'is_active' => true,
            ],
            [
                'code' => 'event_reminder',
                'name' => 'Напоминание о мероприятии',
                'channel' => null,
                'title_template' => 'Скоро начнётся {event_title}',
                'body_template' => "Дата: {event_date} {event_time}\nАдрес: {event_address}\n\nПодробности:\n{event_url}",
                'image_url' => null,
                'button_text' => 'Открыть мероприятие',
                'button_url_template' => '{event_url}',
                'is_active' => true,
            ],
            [
                'code' => 'event_cancelled',
                'name' => 'Мероприятие отменено',
                'channel' => null,
                'title_template' => 'Мероприятие отменено',
                'body_template' => "{event_title}\n\nПричина: {cancel_reason}\nДата: {event_date} {event_time}\nАдрес: {event_address}",
                'image_url' => null,
                'button_text' => null,
                'button_url_template' => null,
                'is_active' => true,
            ],
            [
                'code' => 'event_cancelled_quorum',
                'name' => 'Отмена по кворуму',
                'channel' => null,
                'title_template' => 'Мероприятие отменено',
                'body_template' => "{event_title}\n\nНе набралось минимальное число участников.\nДата: {event_date} {event_time}\nАдрес: {event_address}",
                'image_url' => null,
                'button_text' => null,
                'button_url_template' => null,
                'is_active' => true,
            ],
        ];

        foreach ($rows as $row) {
            DB::table('notification_templates')->updateOrInsert(
                [
                    'code' => $row['code'],
                    'channel' => $row['channel'],
                ],
                array_merge($row, [
                    'updated_at' => now(),
                    'created_at' => now(),
                ])
            );
        }
    }
}
