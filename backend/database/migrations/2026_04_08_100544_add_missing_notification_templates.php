<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $templates = [
            [
                'code'                => 'group_invite',
                'name'                => 'Приглашение в группу/пару',
                'channel'             => null,
                'title_template'      => 'Вас пригласили в группу на {event_title}',
                'body_template'       => "👥 Вас приглашают в группу на мероприятие «{event_title}».\n\nДата: {event_date} {event_time}\nАдрес: {event_address}\n\nПримите или отклоните приглашение:\n{event_url}",
                'button_text'         => 'Открыть мероприятие',
                'button_url_template' => '{event_url}',
                'is_active'           => true,
                'created_at'          => $now,
                'updated_at'          => $now,
            ],
            [
                'code'                => 'tournament_team_invite',
                'name'                => 'Приглашение в команду (турнир)',
                'channel'             => null,
                'title_template'      => 'Вас пригласили в команду на {event_title}',
                'body_template'       => "🏆 Капитан команды приглашает вас на турнир «{event_title}».\n\nДата: {event_date} {event_time}\nАдрес: {event_address}\n\n{event_url}",
                'button_text'         => 'Открыть турнир',
                'button_url_template' => '{event_url}',
                'is_active'           => true,
                'created_at'          => $now,
                'updated_at'          => $now,
            ],
            [
                'code'                => 'event_invite',
                'name'                => 'Личное приглашение на мероприятие',
                'channel'             => null,
                'title_template'      => 'Вас пригласили на {event_title}',
                'body_template'       => "📩 Организатор приглашает вас на мероприятие «{event_title}».\n\nДата: {event_date} {event_time}\nАдрес: {event_address}\n\n{event_url}",
                'button_text'         => 'Открыть мероприятие',
                'button_url_template' => '{event_url}',
                'is_active'           => true,
                'created_at'          => $now,
                'updated_at'          => $now,
            ],
        ];

        foreach ($templates as $tpl) {
            $exists = DB::table('notification_templates')
                ->where('code', $tpl['code'])
                ->exists();

            if (!$exists) {
                DB::table('notification_templates')->insert($tpl);
            }
        }
    }

    public function down(): void
    {
        DB::table('notification_templates')
            ->whereIn('code', ['group_invite', 'tournament_team_invite', 'event_invite'])
            ->delete();
    }
};
