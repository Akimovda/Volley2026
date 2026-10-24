<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ручная отметка «кто подаёт первым» в партии — для табло, когда очков ещё нет.
 * Живёт ОТДЕЛЬНО от match_rally_events / match_lineup_events: не получает seq и не участвует в undoLast.
 * Одна строка на (матч, партия); смена отметки — UPDATE, каждое изменение пишется в match_action_log.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_serve_marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('tournament_matches')->cascadeOnDelete();
            $table->unsignedTinyInteger('set_number');
            $table->foreignId('team_id')->constrained('event_teams')->cascadeOnDelete();
            $table->uuid('client_request_id')->nullable();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('recorded_by_link_id')->nullable()->constrained('broadcast_links')->nullOnDelete();
            $table->string('recorded_by_name')->nullable();
            $table->timestamps();

            $table->unique(['match_id', 'set_number'], 'msm_match_set_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_serve_marks');
    }
};
