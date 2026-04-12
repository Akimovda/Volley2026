<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('premium_subscriptions', function (Blueprint $table) {
            // Недельная сводка вкл/выкл
            $table->boolean('weekly_digest')->default(true)->after('payment_id');
            // Фильтр по уровню игры (null = любой)
            $table->unsignedTinyInteger('notify_level_min')->nullable()->after('weekly_digest');
            $table->unsignedTinyInteger('notify_level_max')->nullable()->after('notify_level_min');
            // Город для сводки (null = город пользователя)
            $table->unsignedBigInteger('notify_city_id')->nullable()->after('notify_level_max');
        });
    }

    public function down(): void
    {
        Schema::table('premium_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['weekly_digest', 'notify_level_min', 'notify_level_max', 'notify_city_id']);
        });
    }
};
