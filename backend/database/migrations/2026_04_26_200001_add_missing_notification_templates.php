<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $templates = [
        [
            'code'                => 'registration_failed',
            'channel'             => null,
            'name'                => 'Ошибка записи на мероприятие',
            'title_template'      => '⚠️ Не удалось записаться на {event_title}',
            'body_template'       => "К сожалению, запись на мероприятие «{event_title}» не состоялась.\n\nПричина: {reason}\n\nДата: {event_date} {event_time}\nАдрес: {location_full}\n\nПопробуйте записаться позже:\n{event_url}",
            'image_url'           => null,
            'button_text'         => 'Открыть мероприятие',
            'button_url_template' => '{event_url}',
            'is_active'           => true,
        ],
        [
            'code'                => 'friend_joined_event',
            'channel'             => null,
            'name'                => 'Знакомый записался на мероприятие',
            'title_template'      => '🤝 {friend_name} записался(ась) на мероприятие',
            'body_template'       => "Ваш знакомый «{friend_name}» записался на «{event_title}».\n\nДата: {event_date} {event_time}\nАдрес: {location_full}\n\nЗаписаться тоже:\n{event_url}",
            'image_url'           => null,
            'button_text'         => 'Открыть мероприятие',
            'button_url_template' => '{event_url}',
            'is_active'           => true,
        ],
        [
            'code'                => 'ad_event_payment_pending',
            'channel'             => null,
            'name'                => 'Ожидание подтверждения оплаты рекламы (содержание задаётся динамически)',
            'title_template'      => null,
            'body_template'       => null,
            'image_url'           => null,
            'button_text'         => null,
            'button_url_template' => null,
            'is_active'           => false,
        ],
        [
            'code'                => 'admin_broadcast',
            'channel'             => null,
            'name'                => 'Рассылка от администратора (содержание задаётся при создании рассылки)',
            'title_template'      => null,
            'body_template'       => null,
            'image_url'           => null,
            'button_text'         => null,
            'button_url_template' => null,
            'is_active'           => false,
        ],
    ];

    public function up(): void
    {
        $now = now();

        foreach ($this->templates as $tpl) {
            $exists = DB::table('notification_templates')
                ->where('code', $tpl['code'])
                ->exists();

            if (!$exists) {
                DB::table('notification_templates')->insert(array_merge($tpl, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }
    }

    public function down(): void
    {
        $codes = array_column($this->templates, 'code');
        DB::table('notification_templates')->whereIn('code', $codes)->delete();
    }
};
