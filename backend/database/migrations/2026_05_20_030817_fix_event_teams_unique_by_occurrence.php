<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_teams', function (Blueprint $table) {
            $table->dropUnique('event_teams_event_id_name_unique');
            $table->unique(['event_id', 'occurrence_id', 'name'], 'event_teams_event_id_occurrence_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('event_teams', function (Blueprint $table) {
            $table->dropUnique('event_teams_event_id_occurrence_name_unique');
            $table->unique(['event_id', 'name'], 'event_teams_event_id_name_unique');
        });
    }
};
