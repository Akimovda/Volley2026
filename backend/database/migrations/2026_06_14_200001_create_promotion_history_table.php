<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_history', function (Blueprint $table) {
            $table->id();

            $table->foreignId('season_id')->constrained('tournament_seasons')->cascadeOnDelete();
            $table->foreignId('occurrence_id')->nullable()->constrained('event_occurrences')->nullOnDelete();
            $table->unsignedSmallInteger('round_number')->nullable();

            // Кого переместили
            $table->foreignId('league_team_id')->constrained('tournament_league_teams')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('event_teams')->nullOnDelete();

            // Откуда → куда (дивизионы)
            $table->foreignId('from_division_id')->nullable()->constrained('tournament_leagues')->nullOnDelete();
            $table->foreignId('to_division_id')->nullable()->constrained('tournament_leagues')->nullOnDelete();

            // Откуда → куда (лиги верхнего уровня)
            $table->foreignId('from_league_id')->nullable()->constrained('leagues')->nullOnDelete();
            $table->foreignId('to_league_id')->nullable()->constrained('leagues')->nullOnDelete();

            // Тип действия
            $table->string('action', 30);

            // Статус подтверждения
            $table->string('status', 20)->default('completed');

            // Инициатор
            $table->string('initiated_by', 20)->default('system');

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['season_id', 'round_number'], 'idx_promotion_history_season');
            $table->index('user_id', 'idx_promotion_history_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_history');
    }
};
