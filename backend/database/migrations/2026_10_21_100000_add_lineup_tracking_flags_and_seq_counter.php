<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Учёт стартового состава и замен в матче: переключатель в настройках турнира/игры
 * (у существующих событий — false), счётчик сквозной нумерации действий матча
 * и «позиции/играл» в статистике игрока (NULL = состав в этой партии не вёлся).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_tournament_settings', function (Blueprint $table) {
            $table->boolean('track_lineup')->default(false)->after('require_libero');
        });

        Schema::table('event_game_settings', function (Blueprint $table) {
            $table->boolean('track_lineup')->default(false);
        });

        Schema::table('tournament_matches', function (Blueprint $table) {
            $table->unsignedInteger('last_seq')->default(0);
        });

        Schema::table('match_player_stats', function (Blueprint $table) {
            $table->json('positions')->nullable();
            $table->boolean('played')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('match_player_stats', function (Blueprint $table) {
            $table->dropColumn(['positions', 'played']);
        });
        Schema::table('tournament_matches', function (Blueprint $table) {
            $table->dropColumn('last_seq');
        });
        Schema::table('event_game_settings', function (Blueprint $table) {
            $table->dropColumn('track_lineup');
        });
        Schema::table('event_tournament_settings', function (Blueprint $table) {
            $table->dropColumn('track_lineup');
        });
    }
};
