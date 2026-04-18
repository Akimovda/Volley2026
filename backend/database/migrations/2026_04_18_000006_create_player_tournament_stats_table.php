<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_tournament_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('event_teams')->cascadeOnDelete();

            $table->unsignedSmallInteger('matches_played')->default(0);
            $table->unsignedSmallInteger('matches_won')->default(0);
            $table->unsignedSmallInteger('sets_won')->default(0);
            $table->unsignedSmallInteger('sets_lost')->default(0);
            $table->unsignedInteger('points_scored')->default(0);
            $table->unsignedInteger('points_conceded')->default(0);

            $table->decimal('match_win_rate', 5, 2)->default(0);
            $table->decimal('set_win_rate', 5, 2)->default(0);
            $table->integer('point_diff')->default(0);

            $table->timestamps();

            $table->unique(['event_id', 'user_id', 'team_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_tournament_stats');
    }
};
