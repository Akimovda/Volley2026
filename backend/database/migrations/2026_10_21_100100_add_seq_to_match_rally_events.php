<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Сквозная нумерация действий матча (очки + события состава): match_rally_events.seq,
 * счётчик — tournament_matches.last_seq (выдаётся под lockForUpdate матча).
 * Бэкфилл: порядок (set_number, id) — тот же, в котором очки шли фактически.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('match_rally_events', function (Blueprint $table) {
            $table->unsignedInteger('seq')->nullable()->after('match_id');
            $table->string('recorded_by_name')->nullable();
            $table->unsignedBigInteger('recorded_by_link_id')->nullable(); // без FK: таблица ссылок появится позже
        });

        DB::statement('
            UPDATE match_rally_events m
            SET seq = r.rn
            FROM (
                SELECT id, ROW_NUMBER() OVER (PARTITION BY match_id ORDER BY set_number, id) AS rn
                FROM match_rally_events
            ) r
            WHERE r.id = m.id
        ');

        DB::statement('
            UPDATE tournament_matches t
            SET last_seq = s.mx
            FROM (SELECT match_id, MAX(seq) AS mx FROM match_rally_events GROUP BY match_id) s
            WHERE s.match_id = t.id
        ');

        DB::statement('ALTER TABLE match_rally_events ALTER COLUMN seq SET NOT NULL');

        Schema::table('match_rally_events', function (Blueprint $table) {
            $table->unique(['match_id', 'seq'], 'mre_match_seq_unique');
        });
    }

    public function down(): void
    {
        Schema::table('match_rally_events', function (Blueprint $table) {
            $table->dropUnique('mre_match_seq_unique');
            $table->dropColumn(['seq', 'recorded_by_name', 'recorded_by_link_id']);
        });
    }
};
