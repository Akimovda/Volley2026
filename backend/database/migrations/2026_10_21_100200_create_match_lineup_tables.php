<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Стартовый состав партии, события состава (замена/либеро) и журнал отмен.
 * НЕ путать с team_substitutions — та таблица про лиговую замену между турами.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_set_starts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('tournament_matches')->cascadeOnDelete();
            $table->unsignedTinyInteger('set_number');
            $table->foreignId('first_serve_team_id')->nullable()->constrained('event_teams')->nullOnDelete();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('recorded_by_link_id')->nullable();
            $table->string('recorded_by_name')->nullable();
            $table->timestamps();

            $table->unique(['match_id', 'set_number'], 'mss_match_set_unique');
        });

        Schema::create('match_set_lineups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('tournament_matches')->cascadeOnDelete();
            $table->unsignedTinyInteger('set_number');
            $table->foreignId('team_id')->constrained('event_teams')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('position', 20);
            $table->unsignedSmallInteger('zone')->nullable(); // у либеро NULL
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('recorded_by_link_id')->nullable();
            $table->string('recorded_by_name')->nullable();
            $table->timestamps();

            $table->unique(['match_id', 'set_number', 'team_id', 'user_id'], 'msl_player_unique');
            $table->index(['match_id', 'set_number']);
        });
        DB::statement(
            'CREATE UNIQUE INDEX msl_zone_unique ON match_set_lineups (match_id, set_number, team_id, zone) WHERE zone IS NOT NULL'
        );

        Schema::create('match_lineup_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('tournament_matches')->cascadeOnDelete();
            $table->unsignedTinyInteger('set_number');
            $table->foreignId('team_id')->constrained('event_teams')->cascadeOnDelete();
            $table->string('type', 20); // substitution | libero_in | libero_out
            $table->foreignId('out_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('in_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('position', 20)->nullable();
            $table->unsignedSmallInteger('zone')->nullable();
            $table->unsignedInteger('seq');
            $table->unsignedSmallInteger('score_home')->default(0); // снимок для показа, не источник правды
            $table->unsignedSmallInteger('score_away')->default(0);
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('recorded_by_link_id')->nullable();
            $table->string('recorded_by_name')->nullable();
            $table->timestamps();

            // Общая с match_rally_events уникальность seq обеспечивается tournament_matches.last_seq под блокировкой
            $table->unique(['match_id', 'seq'], 'mle_match_seq_unique');
            $table->index(['match_id', 'set_number']);
        });

        Schema::create('match_action_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('tournament_matches')->cascadeOnDelete();
            $table->unsignedTinyInteger('set_number')->nullable();
            $table->string('action', 30); // undo_point | undo_lineup_event | ...
            $table->json('payload')->nullable(); // копия удалённой строки
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('recorded_by_link_id')->nullable();
            $table->string('recorded_by_name')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['match_id', 'set_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_action_log');
        Schema::dropIfExists('match_lineup_events');
        DB::statement('DROP INDEX IF EXISTS msl_zone_unique');
        Schema::dropIfExists('match_set_lineups');
        Schema::dropIfExists('match_set_starts');
    }
};
