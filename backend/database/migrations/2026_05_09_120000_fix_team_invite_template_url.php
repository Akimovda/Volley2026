<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('notification_templates')
            ->where('code', 'tournament_team_invite')
            ->update([
                'button_text' => 'Открыть приглашение',
                'button_url_template' => '{invite_url}',
                'body_template' => "🏆 Капитан команды приглашает вас в команду «{team_name}» на турнир «{event_title}».\n\nДата: {event_date} {event_time}\nАдрес: {event_address}\n\n👉 Принять приглашение: {invite_url}",
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('notification_templates')
            ->where('code', 'tournament_team_invite')
            ->update([
                'button_text' => 'Открыть турнир',
                'button_url_template' => '{event_url}',
                'body_template' => "🏆 Капитан команды приглашает вас на турнир «{event_title}».\n\nДата: {event_date} {event_time}\nАдрес: {event_address}",
                'updated_at' => now(),
            ]);
    }
};
