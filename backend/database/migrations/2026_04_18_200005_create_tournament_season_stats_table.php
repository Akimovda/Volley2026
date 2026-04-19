<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_season_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained('tournament_seasons')->cascadeOnDelete();
            $table->foreignId('league_id')->constrained('tournament_leagues')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->unsignedSmallInteger('rounds_played')->default(0);
            $table->unsignedSmallInteger('matches_played')->default(0);
            $table->unsignedSmallInteger('matches_won')->default(0);
            $table->unsignedSmallInteger('sets_won')->default(0);
            $table->unsignedSmallInteger('sets_lost')->default(0);
            $table->unsignedInteger('points_scored')->default(0);
            $table->unsignedInteger('points_conceded')->default(0);
            $table->decimal('match_win_rate', 5, 2)->default(0);
            $table->decimal('set_win_rate', 5, 2)->default(0);
            $table->unsignedSmallInteger('best_placement')->nullable();
            $table->smallInteger('current_streak')->default(0);
            $table->unsignedSmallInteger('elo_season')->default(1500);

            $table->timestamps();

            $table->unique(['season_id', 'league_id', 'user_id']);
            $table->index(['season_id', 'league_id', 'match_win_rate']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_season_stats');
    }
};
