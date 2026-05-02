<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_tiebreakers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stage_id')->constrained('tournament_stages')->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('tournament_groups')->cascadeOnDelete();
            $table->foreignId('team_a_id')->constrained('event_teams')->cascadeOnDelete();
            $table->foreignId('team_b_id')->constrained('event_teams')->cascadeOnDelete();

            $table->enum('method', ['match', 'lottery'])->nullable();
            $table->foreignId('match_id')->nullable()->constrained('tournament_matches')->nullOnDelete();
            $table->foreignId('winner_team_id')->nullable()->constrained('event_teams')->nullOnDelete();

            $table->enum('status', ['pending', 'resolved'])->default('pending');
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            $table->unique(['stage_id', 'group_id', 'team_a_id', 'team_b_id']);
            $table->index(['stage_id', 'group_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_tiebreakers');
    }
};
