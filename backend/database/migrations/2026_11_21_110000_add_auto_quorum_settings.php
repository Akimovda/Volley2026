<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Авто увеличение кворума (docs/auto-quorum.md), этап 3: настройки мероприятия, отметка расширения даты, журнал.
 * Аддитивная: по умолчанию функция выключена — поведение сайта не меняется.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_game_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('event_game_settings', 'auto_quorum_enabled')) {
                $table->boolean('auto_quorum_enabled')->default(false);
            }
            if (!Schema::hasColumn('event_game_settings', 'auto_quorum_players_per_team')) {
                $table->smallInteger('auto_quorum_players_per_team')->nullable();
            }
            if (!Schema::hasColumn('event_game_settings', 'auto_quorum_max_teams')) {
                $table->smallInteger('auto_quorum_max_teams')->nullable();
            }
        });

        if (!Schema::hasColumn('event_occurrences', 'quorum_expanded_at')) {
            Schema::table('event_occurrences', function (Blueprint $table) {
                $table->timestampTz('quorum_expanded_at')->nullable();
            });
        }

        if (!Schema::hasTable('event_occurrence_quorum_log')) {
            Schema::create('event_occurrence_quorum_log', function (Blueprint $table) {
                $table->id();
                $table->foreignId('occurrence_id')->constrained('event_occurrences')->cascadeOnDelete();
                $table->smallInteger('from_teams');
                $table->smallInteger('to_teams');
                $table->integer('total_registered');
                $table->jsonb('promoted_user_ids')->nullable();
                $table->timestampTz('created_at')->useCurrent();
                $table->index('occurrence_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('event_occurrence_quorum_log');
        if (Schema::hasColumn('event_occurrences', 'quorum_expanded_at')) {
            Schema::table('event_occurrences', fn (Blueprint $t) => $t->dropColumn('quorum_expanded_at'));
        }
        Schema::table('event_game_settings', function (Blueprint $table) {
            foreach (['auto_quorum_enabled', 'auto_quorum_players_per_team', 'auto_quorum_max_teams'] as $c) {
                if (Schema::hasColumn('event_game_settings', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
