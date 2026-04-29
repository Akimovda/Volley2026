<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $templates = [
        [
            'code'                => 'organizer_registered_player',
            'channel'             => null,
            'name'                => 'Организатору: вы записали игрока',
            'title_template'      => '{event_title}',
            'body_template'       => "✅ Вы записали:\n\n👤 {player_name}\n☎️ {player_phone}\n\nна позицию: {player_position}\nосталось свободных мест: {available_count}",
            'image_url'           => null,
            'button_text'         => 'Открыть мероприятие',
            'button_url_template' => '{event_url}',
            'is_active'           => true,
        ],
        [
            'code'                => 'organizer_cancelled_player',
            'channel'             => null,
            'name'                => 'Организатору: вы отменили запись игрока',
            'title_template'      => '{event_title}',
            'body_template'       => "⛔️ Вы отменили запись:\n\n👤 {player_name}\n☎️ {player_phone}\n\nна позицию: {player_position}\nосталось свободных мест: {available_count}",
            'image_url'           => null,
            'button_text'         => 'Открыть мероприятие',
            'button_url_template' => '{event_url}',
            'is_active'           => true,
        ],
        [
            'code'                => 'organizer_deleted_player',
            'channel'             => null,
            'name'                => 'Организатору: вы удалили запись игрока',
            'title_template'      => '{event_title}',
            'body_template'       => "🗑 Вы удалили запись:\n\n👤 {player_name}\n☎️ {player_phone}\n\nна позицию: {player_position}\nосталось свободных мест: {available_count}",
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
