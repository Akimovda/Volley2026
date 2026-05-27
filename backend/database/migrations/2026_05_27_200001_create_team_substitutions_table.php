<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_substitutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained('tournament_leagues')->cascadeOnDelete();
            $table->foreignId('occurrence_id')->constrained('event_occurrences')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('event_teams')->cascadeOnDelete();
            $table->foreignId('original_player_id')->constrained('users');
            $table->foreignId('substitute_player_id')->constrained('users');
            // 'reserve' = из резерва лиги | 'external' = не из лиги
            $table->string('substitute_source', 20);
            // 'captain' = капитан пригласил | 'substitute' = игрок предложил себя
            $table->string('initiated_by', 20);
            $table->timestamp('captain_confirmed_at')->nullable();
            $table->timestamp('substitute_confirmed_at')->nullable();
            // pending | confirmed | applied | cancelled | expired
            $table->string('status', 20)->default('pending');
            $table->timestamps();

            $table->unique(['occurrence_id', 'team_id']);
            $table->index(['league_id', 'status']);
            $table->index(['substitute_player_id', 'occurrence_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_substitutions');
    }
};
