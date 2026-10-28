<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Минимум игроков для проведения турнира с ИНДИВИДУАЛЬНОЙ регистрацией (команды формируются позже).
        // NULL = порога нет, авто-отмена по кворуму не применяется. Отдельно от event_game_settings.min_players,
        // где у существующих турниров лежат системные значения по умолчанию (6), а не решение организатора.
        Schema::table('event_tournament_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('min_players')->nullable()->after('min_teams');
        });
    }

    public function down(): void
    {
        Schema::table('event_tournament_settings', function (Blueprint $table) {
            $table->dropColumn('min_players');
        });
    }
};
