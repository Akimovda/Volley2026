<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Повтор анонса в каналы (кемпы, мастер-классы, турниры): раз в 7 или 14 дней после предыдущей отправки,
 * пока открыта регистрация. NULL — не повторять (анонс уходит один раз, при открытии регистрации).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('event_notification_channels') && !Schema::hasColumn('event_notification_channels', 'repeat_interval_days')) {
            Schema::table('event_notification_channels', function (Blueprint $table) {
                $table->unsignedSmallInteger('repeat_interval_days')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('event_notification_channels', 'repeat_interval_days')) {
            Schema::table('event_notification_channels', function (Blueprint $table) {
                $table->dropColumn('repeat_interval_days');
            });
        }
    }
};
