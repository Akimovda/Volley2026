<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_league_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained('tournament_leagues')->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('event_teams')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('active'); // active | promoted | relegated | eliminated | reserve
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->unsignedSmallInteger('reserve_position')->nullable();
            $table->timestamps();

            $table->index(['league_id', 'status']);
            $table->index(['league_id', 'reserve_position']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_league_teams');
    }
};
