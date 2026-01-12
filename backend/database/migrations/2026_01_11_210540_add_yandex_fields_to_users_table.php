<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // если yandex_id уже есть — не добавляй повторно
            if (!Schema::hasColumn('users', 'yandex_id')) {
                $table->string('yandex_id')->nullable()->unique('users_yandex_id_unique');
            } else {
                // на всякий случай: уникальный индекс (если вдруг колонка есть, а индекса нет)
                // (Если индекс уже есть — этот блок лучше не выполнять)
                // $table->unique('yandex_id', 'users_yandex_id_unique');
            }

            if (!Schema::hasColumn('users', 'yandex_phone')) {
                $table->string('yandex_phone')->nullable();
            }

            if (!Schema::hasColumn('users', 'yandex_avatar')) {
                $table->string('yandex_avatar')->nullable();
            }

            // (рекомендую) хранить также email от Яндекса отдельно
            if (!Schema::hasColumn('users', 'yandex_email')) {
                $table->string('yandex_email')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'yandex_id')) {
                // имя индекса важно такое же, как в up()
                try { $table->dropUnique('users_yandex_id_unique'); } catch (\Throwable $e) {}
                $table->dropColumn('yandex_id');
            }

            if (Schema::hasColumn('users', 'yandex_phone')) {
                $table->dropColumn('yandex_phone');
            }

            if (Schema::hasColumn('users', 'yandex_avatar')) {
                $table->dropColumn('yandex_avatar');
            }

            if (Schema::hasColumn('users', 'yandex_email')) {
                $table->dropColumn('yandex_email');
            }
        });
    }
};
