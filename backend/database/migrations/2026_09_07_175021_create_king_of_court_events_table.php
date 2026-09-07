<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('king_of_court_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stage_id')->constrained('tournament_stages')->cascadeOnDelete();
            $table->unsignedTinyInteger('round_number');
            $table->string('event_type', 20)->comment('round_start|king_point|fault|takeover');
            $table->foreignId('team_id')->nullable()->constrained('event_teams')->nullOnDelete()
                ->comment('команда-герой события: кто набрал очко/кто вытеснил короля/кто ошибся на подаче');
            $table->foreignId('king_team_id')->nullable()->constrained('event_teams')->nullOnDelete()
                ->comment('снимок состояния ПОСЛЕ этого события');
            $table->foreignId('challenger_team_id')->nullable()->constrained('event_teams')->nullOnDelete()
                ->comment('снимок состояния ПОСЛЕ этого события');
            $table->json('queue')->comment('снимок очереди команд ПОСЛЕ этого события');
            $table->json('round_points')->comment('снимок очков текущего раунда {team_id: points} ПОСЛЕ этого события');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['stage_id', 'round_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('king_of_court_events');
    }
};
