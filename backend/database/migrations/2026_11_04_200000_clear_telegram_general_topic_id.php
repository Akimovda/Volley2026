<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Тема №1 — главная тема форума Telegram: Bot API не принимает message_thread_id=1 («message thread not found»).
 * Чистим ранее сохранённые «1»/«0»: анонс уходит в главную тему, когда номер темы не задан.
 */
return new class extends Migration {
    public function up(): void
    {
        DB::table('event_notification_channels')->whereIn('message_thread_id', [0, 1])->update(['message_thread_id' => null]);

        // привязка темы к чату (user_notification_channels.meta.message_thread_id)
        DB::table('user_notification_channels')->whereNotNull('meta')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $meta = json_decode((string) $row->meta, true);
                if (is_array($meta) && array_key_exists('message_thread_id', $meta) && in_array((int) $meta['message_thread_id'], [0, 1], true)) {
                    unset($meta['message_thread_id']);
                    DB::table('user_notification_channels')->where('id', $row->id)->update(['meta' => json_encode($meta, JSON_UNESCAPED_UNICODE)]);
                }
            }
        });
    }

    public function down(): void
    {
        // значения не восстанавливаем: «1» никогда не работало
    }
};
