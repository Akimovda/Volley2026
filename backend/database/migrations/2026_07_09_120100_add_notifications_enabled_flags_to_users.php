<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // По аналогии с уже существующим max_notifications_enabled — тот заводился
            // при бинде/анбинде MAX, но никогда не читался как гейт (баг, чинится тем же
            // коммитом). telegram_id/vk_notify_user_id НЕ трогаем при постоянной ошибке —
            // это устойчивые идентификаторы, флаг только выключает отправку, не рвёт связь.
            $table->boolean('telegram_notifications_enabled')->default(true)->after('telegram_notify_linked_at');
            $table->boolean('vk_notifications_enabled')->default(true)->after('vk_notify_linked_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['telegram_notifications_enabled', 'vk_notifications_enabled']);
        });
    }
};
