<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Авто увеличение кворума (docs/auto-quorum.md), этап 2: число команд КОНКРЕТНОЙ даты после расширения.
 * NULL = дата не расширялась, ёмкость как у мероприятия (поведение не меняется). На этапе 2 никто не заполняет.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('event_occurrences', 'quorum_teams_count')) {
            Schema::table('event_occurrences', function (Blueprint $table) {
                $table->smallInteger('quorum_teams_count')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('event_occurrences', 'quorum_teams_count')) {
            Schema::table('event_occurrences', function (Blueprint $table) {
                $table->dropColumn('quorum_teams_count');
            });
        }
    }
};
