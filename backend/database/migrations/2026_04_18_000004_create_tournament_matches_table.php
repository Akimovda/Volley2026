<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stage_id')->constrained('tournament_stages')->cascadeOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('tournament_groups')->nullOnDelete();
            $table->unsignedSmallInteger('round')->default(1);
            $table->string('bracket_position')->nullable();
            $table->unsignedSmallInteger('match_number')->default(1);

            $table->foreignId('team_home_id')->nullable()->constrained('event_teams')->nullOnDelete();
            $table->foreignId('team_away_id')->nullable()->constrained('event_teams')->nullOnDelete();

            $table->string('court')->nullable();
            $table->timestamp('scheduled_at')->nullable();

            $table->string('status')->default('scheduled');
            $table->foreignId('winner_team_id')->nullable()->constrained('event_teams')->nullOnDelete();

            $table->jsonb('score_home')->nullable();
            $table->jsonb('score_away')->nullable();
            $table->unsignedSmallInteger('sets_home')->default(0);
            $table->unsignedSmallInteger('sets_away')->default(0);
            $table->unsignedSmallInteger('total_points_home')->default(0);
            $table->unsignedSmallInteger('total_points_away')->default(0);

            $table->foreignId('next_match_id')->nullable()->constrained('tournament_matches')->nullOnDelete();
            $table->string('next_match_slot')->nullable();
            $table->foreignId('loser_next_match_id')->nullable()->constrained('tournament_matches')->nullOnDelete();
            $table->string('loser_next_match_slot')->nullable();

            $table->foreignId('scored_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('scored_at')->nullable();

            $table->timestamps();

            $table->index(['stage_id', 'round']);
            $table->index(['stage_id', 'group_id']);
            $table->index('status');
            $table->index('scheduled_at');
            $table->index('team_home_id');
            $table->index('team_away_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_matches');
    }
};
