<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_career_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('direction');

            $table->unsignedSmallInteger('total_tournaments')->default(0);
            $table->unsignedSmallInteger('total_matches')->default(0);
            $table->unsignedSmallInteger('total_wins')->default(0);
            $table->unsignedSmallInteger('total_sets_won')->default(0);
            $table->unsignedSmallInteger('total_sets_lost')->default(0);
            $table->unsignedInteger('total_points_scored')->default(0);
            $table->unsignedInteger('total_points_conceded')->default(0);

            $table->decimal('match_win_rate', 5, 2)->default(0);
            $table->decimal('set_win_rate', 5, 2)->default(0);
            $table->unsignedSmallInteger('best_placement')->nullable();
            $table->unsignedSmallInteger('elo_rating')->default(1500);

            $table->timestamps();

            $table->unique(['user_id', 'direction']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_career_stats');
    }
};
