<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // «Игра со статистикой»: организатор ведёт матчи, счёт и статистику игроков (format=game)
            $table->boolean('collect_stats')->default(false);
            // «Рейтинговое мероприятие»: результаты таких игр идут в общие рейтинги (по умолчанию нет)
            $table->boolean('stats_rated')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['collect_stats', 'stats_rated']);
        });
    }
};
