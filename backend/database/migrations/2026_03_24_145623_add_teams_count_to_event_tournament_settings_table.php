<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_tournament_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('teams_count')
                ->default(4)
                ->after('reserve_players_max');
        });
    }

    public function down(): void
    {
        Schema::table('event_tournament_settings', function (Blueprint $table) {
            $table->dropColumn('teams_count');
        });
    }
};
