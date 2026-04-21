<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. tournament_stages — к какому туру (occurrence) относится стадия
        Schema::table('tournament_stages', function (Blueprint $table) {
            $table->unsignedBigInteger('occurrence_id')->nullable()->after('event_id');
            $table->foreign('occurrence_id')
                  ->references('id')->on('event_occurrences')
                  ->nullOnDelete();
            $table->index('occurrence_id');
        });

        // 2. player_tournament_stats — статистика за конкретный тур
        Schema::table('player_tournament_stats', function (Blueprint $table) {
            $table->unsignedBigInteger('occurrence_id')->nullable()->after('event_id');
            $table->foreign('occurrence_id')
                  ->references('id')->on('event_occurrences')
                  ->nullOnDelete();
            $table->index('occurrence_id');
        });

        // 3. tournament_season_events — связка сезон ↔ occurrence
        Schema::table('tournament_season_events', function (Blueprint $table) {
            $table->unsignedBigInteger('occurrence_id')->nullable()->after('event_id');
            $table->foreign('occurrence_id')
                  ->references('id')->on('event_occurrences')
                  ->nullOnDelete();
            $table->index('occurrence_id');
        });
    }

    public function down(): void
    {
        foreach (['tournament_stages', 'player_tournament_stats', 'tournament_season_events'] as $tbl) {
            Schema::table($tbl, function (Blueprint $table) {
                $table->dropForeign(['occurrence_id']);
                $table->dropIndex(['occurrence_id']);
                $table->dropColumn('occurrence_id');
            });
        }
    }
};
