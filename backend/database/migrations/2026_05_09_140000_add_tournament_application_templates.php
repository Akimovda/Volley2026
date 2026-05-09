<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $now = now();

        $templates = [
            [
                'code'                => 'tournament_application_incomplete',
                'name'                => 'Заявка с неполным составом (организатору)',
                'channel'             => null,
                'title_template'      => 'Заявка с неполным составом — {team_name}',
                'body_template'       => "📝 Команда «{team_name}» подала заявку на турнир «{event_title}» до окончания формирования состава.\n\nЕсли состав не будет собран к окончанию приёма заявок — заявка будет отклонена автоматически.\n\n👉 Управление турниром: {button_url}",
                'button_text'         => 'Управление турниром',
                'button_url_template' => '{button_url}',
                'is_active'           => true,
                'created_at'          => $now,
                'updated_at'          => $now,
            ],
            [
                'code'                => 'tournament_application_completed',
                'name'                => 'Команда завершила формирование состава (организатору)',
                'channel'             => null,
                'title_template'      => 'Команда «{team_name}» собрала состав',
                'body_template'       => "✅ Команда «{team_name}» завершила формирование состава.\n\nЗаявка на турнир «{event_title}» ожидает вашего решения.\n\n👉 Управление турниром: {button_url}",
                'button_text'         => 'Управление турниром',
                'button_url_template' => '{button_url}',
                'is_active'           => true,
                'created_at'          => $now,
                'updated_at'          => $now,
            ],
            [
                'code'                => 'tournament_application_auto_rejected',
                'name'                => 'Заявка автоматически отклонена (капитану)',
                'channel'             => null,
                'title_template'      => 'Заявка отклонена автоматически — {event_title}',
                'body_template'       => "❌ Заявка команды «{team_name}» на турнир «{event_title}» отклонена автоматически.\n\nПричина: состав не сформирован к окончанию приёма заявок.\n\n👉 Открыть команду: {button_url}",
                'button_text'         => 'Открыть команду',
                'button_url_template' => '{button_url}',
                'is_active'           => true,
                'created_at'          => $now,
                'updated_at'          => $now,
            ],
        ];

        foreach ($templates as $tpl) {
            DB::table('notification_templates')->updateOrInsert(
                ['code' => $tpl['code']],
                $tpl
            );
        }
    }

    public function down(): void
    {
        DB::table('notification_templates')
            ->whereIn('code', [
                'tournament_application_incomplete',
                'tournament_application_completed',
                'tournament_application_auto_rejected',
            ])
            ->delete();
    }
};
