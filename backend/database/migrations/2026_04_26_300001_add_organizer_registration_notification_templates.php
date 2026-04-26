<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $templates = [
        [
            'code'                => 'organizer_player_registered',
            'channel'             => null,
            'name'                => 'Организатору: игрок записался на мероприятие',
            'title_template'      => '✅ Регистрация подтверждена',
            'body_template'       => "✅ Регистрация подтверждена\n\n🏐 {event_title}\n\nИнформация:\n\n📆: {event_date}\n🕘: {event_time}\n📍: {location_full}\n\nСейчас {booked_count} мест(о) забронировано, а {available_count} доступно.\n\nДетали записи:\n\n👤 : {player_name}\n☎️ : {player_phone}\n✅ {player_position}",
            'image_url'           => null,
            'button_text'         => 'Открыть мероприятие',
            'button_url_template' => '{event_url}',
            'is_active'           => true,
        ],
        [
            'code'                => 'organizer_player_cancelled',
            'channel'             => null,
            'name'                => 'Организатору: игрок отменил запись',
            'title_template'      => '⛔️ Бронь отменена',
            'body_template'       => "⛔️ Бронь отменена\n\n🏐 {event_title}\n\nИнформация:\n\n📆: {event_date}\n🕘: {event_time}\n📍: {location_full}\n\nСейчас {booked_count} мест(о) забронировано, а {available_count} доступно.\n\nДетали записи:\n\n👤 : {player_name}\n☎️ : {player_phone}\n✅ {player_position}",
            'image_url'           => null,
            'button_text'         => 'Открыть мероприятие',
            'button_url_template' => '{event_url}',
            'is_active'           => true,
        ],
    ];

    public function up(): void
    {
        $now = now();
        foreach ($this->templates as $tpl) {
            if (!DB::table('notification_templates')->where('code', $tpl['code'])->exists()) {
                DB::table('notification_templates')->insert(array_merge($tpl, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }
    }

    public function down(): void
    {
        DB::table('notification_templates')
            ->whereIn('code', array_column($this->templates, 'code'))
            ->delete();
    }
};
