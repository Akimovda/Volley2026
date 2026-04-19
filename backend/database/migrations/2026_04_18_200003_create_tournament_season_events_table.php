<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_season_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained('tournament_seasons')->cascadeOnDelete();
            $table->foreignId('league_id')->constrained('tournament_leagues')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->unsignedSmallInteger('round_number')->default(1);
            $table->string('status')->default('pending'); // pending | completed
            $table->timestamps();

            $table->unique(['season_id', 'event_id']);
            $table->index(['league_id', 'round_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_season_events');
    }
};
