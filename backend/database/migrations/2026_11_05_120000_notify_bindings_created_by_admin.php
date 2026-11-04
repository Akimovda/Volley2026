<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Привязки мессенджеров (Telegram/VK/MAX), созданные админом под чужим аккаунтом (impersonate), помечаются:
 * created_by_admin_id — id админа. При завершении привязки отказываем, если мессенджер принадлежит самому админу
 * (иначе личные уведомления чужого аккаунта уходят админу — случай 29.10.2026, Широков).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['telegram_notify_bindings', 'vk_notify_bindings', 'max_bindings'] as $t) {
            if (Schema::hasTable($t) && !Schema::hasColumn($t, 'created_by_admin_id')) {
                Schema::table($t, function (Blueprint $table) {
                    $table->unsignedBigInteger('created_by_admin_id')->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['telegram_notify_bindings', 'vk_notify_bindings', 'max_bindings'] as $t) {
            if (Schema::hasTable($t) && Schema::hasColumn($t, 'created_by_admin_id')) {
                Schema::table($t, function (Blueprint $table) {
                    $table->dropColumn('created_by_admin_id');
                });
            }
        }
    }
};
