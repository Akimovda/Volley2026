<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('event_game_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('reserve_players_max')->nullable()->after('max_players');
        });
    }

    public function down(): void
    {
        Schema::table('event_game_settings', function (Blueprint $table) {
            $table->dropColumn('reserve_players_max');
        });
    }
};
