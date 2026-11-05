<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Тема (топик) Telegram-группы для уведомлений организатору о записях игроков — отдельно от темы анонсов канала.
 * NULL — как раньше: тема канала (если задана); число > 1 — эта тема; 1 — главная тема группы.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'registration_notify_thread_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('registration_notify_thread_id')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'registration_notify_thread_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('registration_notify_thread_id');
            });
        }
    }
};
