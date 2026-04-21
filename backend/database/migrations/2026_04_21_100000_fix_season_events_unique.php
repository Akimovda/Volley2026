<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_season_events', function (Blueprint $table) {
            $table->dropUnique('tournament_season_events_season_id_event_id_unique');
            $table->unique(['season_id', 'occurrence_id'], 'tse_season_occurrence_unique');
            $table->index(['season_id', 'event_id'], 'tse_season_event_index');
        });
    }

    public function down(): void
    {
        Schema::table('tournament_season_events', function (Blueprint $table) {
            $table->dropUnique('tse_season_occurrence_unique');
            $table->dropIndex('tse_season_event_index');
            $table->unique(['season_id', 'event_id'], 'tournament_season_events_season_id_event_id_unique');
        });
    }
};
