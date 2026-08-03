<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_matches', function (Blueprint $table) {
            // Дедуп: TournamentEloService::recalculateForEvent() проходит ВСЕ
            // завершённые матчи события заново при каждом вызове (в отличие от
            // stats_processed_at/OpenSkill, тут не было своего guard'а) — если
            // турнир завершается повторно (например, стадия плей-офф дозаполнена
            // матчем позже, после того как турнир уже считался завершённым),
            // Elo для старых матчей задваивался. Аналогичный паттерн, что и у
            // stats_processed_at.
            $table->timestamp('elo_processed_at')->nullable()->after('stats_processed_at');
        });
    }

    public function down(): void
    {
        Schema::table('tournament_matches', function (Blueprint $table) {
            $table->dropColumn('elo_processed_at');
        });
    }
};
