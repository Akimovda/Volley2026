<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_player_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('tournament_matches')->cascadeOnDelete();
            $table->unsignedSmallInteger('set_number')->default(0)->comment('0 = весь матч, 1-5 = конкретный сет');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('event_teams')->cascadeOnDelete();

            // Подача
            $table->unsignedSmallInteger('serves_total')->default(0);
            $table->unsignedSmallInteger('aces')->default(0);
            $table->unsignedSmallInteger('serve_errors')->default(0);

            // Атака
            $table->unsignedSmallInteger('attacks_total')->default(0);
            $table->unsignedSmallInteger('kills')->default(0);
            $table->unsignedSmallInteger('attack_errors')->default(0);

            // Блок
            $table->unsignedSmallInteger('blocks')->default(0);
            $table->unsignedSmallInteger('block_errors')->default(0);

            // Приём
            $table->unsignedSmallInteger('digs')->default(0);
            $table->unsignedSmallInteger('reception_errors')->default(0);

            // Передача
            $table->unsignedSmallInteger('assists')->default(0);

            // Итоговые очки (aces + kills + blocks)
            $table->unsignedSmallInteger('points_scored')->default(0);

            $table->timestamps();

            $table->unique(['match_id', 'set_number', 'user_id'], 'mps_match_set_user_unique');
            $table->index(['user_id', 'team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_player_stats');
    }
};
