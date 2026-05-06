<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_tiebreaker_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stage_id')->constrained('tournament_stages')->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('tournament_groups')->cascadeOnDelete();
            $table->json('team_ids');
            $table->string('team_ids_key', 200);
            $table->enum('method', ['full_diff', 'match', 'lottery'])->nullable();
            $table->json('match_settings')->nullable();
            $table->json('resolved_order')->nullable();
            $table->enum('status', ['pending', 'resolved'])->default('pending');
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['stage_id', 'group_id', 'team_ids_key'], 'tts_unique_set');
            $table->index(['stage_id', 'group_id', 'status'], 'tts_stage_group_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_tiebreaker_sets');
    }
};
