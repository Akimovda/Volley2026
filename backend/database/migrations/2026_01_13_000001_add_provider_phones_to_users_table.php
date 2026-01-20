<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Важно: используем hasColumn(), чтобы миграция была безопасной при повторном запуске
            if (!Schema::hasColumn('users', 'telegram_phone')) {
                $table->string('telegram_phone', 64)->nullable()->after('telegram_username');
            }

            if (!Schema::hasColumn('users', 'vk_phone')) {
                $table->string('vk_phone', 64)->nullable()->after('vk_email');
            }

            if (!Schema::hasColumn('users', 'yandex_phone')) {
                $table->string('yandex_phone', 64)->nullable()->after('yandex_email');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // down тоже безопасный
            $cols = [];

            if (Schema::hasColumn('users', 'telegram_phone')) $cols[] = 'telegram_phone';
            if (Schema::hasColumn('users', 'vk_phone'))       $cols[] = 'vk_phone';
            if (Schema::hasColumn('users', 'yandex_phone'))   $cols[] = 'yandex_phone';

            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
