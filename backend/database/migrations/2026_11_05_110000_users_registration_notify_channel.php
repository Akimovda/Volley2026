<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Куда слать уведомления организатору о записях игроков: привязанный канал/группа (с темой) вместо личных сообщений бота.
 * registration_notify_channel_id — user_notification_channels.id (NULL = как раньше, личные сообщения);
 * registration_notify_also_dm — дублировать ещё и в личные сообщения.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('registration_notify_channel_id')->nullable();
            $table->boolean('registration_notify_also_dm')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['registration_notify_channel_id', 'registration_notify_also_dm']);
        });
    }
};
