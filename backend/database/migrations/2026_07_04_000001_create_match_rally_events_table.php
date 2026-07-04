<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_rally_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('tournament_matches')->cascadeOnDelete();
            $table->unsignedTinyInteger('set_number')->comment('1-5, агрегат за весь матч считается на лету');
            $table->foreignId('team_id')->constrained('event_teams')->cascadeOnDelete()
                ->comment('команда, выигравшая розыгрыш');
            $table->unsignedSmallInteger('team_point_number')->comment('счёт team_id в этом сете после этого очка');
            $table->string('action_type', 20);
            $table->foreignId('player_id')->nullable()->constrained('users')->nullOnDelete()
                ->comment('игрок, которому засчитан стат');
            $table->foreignId('stat_team_id')->nullable()->constrained('event_teams')->nullOnDelete()
                ->comment('команда player_id — может отличаться от team_id для opp_*_error');
            $table->foreignId('dig_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assist_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['match_id', 'set_number', 'team_id', 'team_point_number'], 'mre_match_set_team_point_unique');
            $table->index(['match_id', 'set_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_rally_events');
    }
};
