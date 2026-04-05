<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Включён ли «Помощник записи» для этого мероприятия
            $table->boolean('bot_assistant_enabled')->default(false)->after('registration_mode');

            // Порог запуска: если за первые сутки записалось меньше X% — боты включаются
            // Диапазон 5–30, по умолчанию 10
            $table->unsignedTinyInteger('bot_assistant_threshold')->default(10)->after('bot_assistant_enabled');

            // Максимум мест которые боты могут занять одновременно (% от max_players)
            // По умолчанию 40% — чтобы не забивать зал
            $table->unsignedTinyInteger('bot_assistant_max_fill_pct')->default(40)->after('bot_assistant_threshold');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'bot_assistant_enabled',
                'bot_assistant_threshold',
                'bot_assistant_max_fill_pct',
            ]);
        });
    }
};
