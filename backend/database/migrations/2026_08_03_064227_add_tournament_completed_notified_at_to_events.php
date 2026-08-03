<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Дедуп: TournamentController::checkStageCompletion() отправляет
            // "Турнир завершён!" всем подтверждённым участникам каждый раз, когда
            // allStages===completedStages становится true — без guard'а это
            // срабатывает повторно, если в турнир позже добавляется и завершается
            // ещё один матч (например, дозаполнение плей-офф). Атомарный claim по
            // тому же паттерну, что city_notified_at.
            $table->timestamp('tournament_completed_notified_at')->nullable()->after('season_id');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('tournament_completed_notified_at');
        });
    }
};
