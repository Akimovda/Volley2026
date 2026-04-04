<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_tournament_settings', function (Blueprint $table) {
            $table->string('game_scheme', 32)->nullable()->after('registration_mode');
            $table->unsignedSmallInteger('reserve_players_max')->nullable()->after('team_size_max');
            $table->unsignedSmallInteger('total_players_max')->nullable()->after('reserve_players_max');
        });
    }

    public function down(): void
    {
        Schema::table('event_tournament_settings', function (Blueprint $table) {
            $table->dropColumn([
                'game_scheme',
                'reserve_players_max',
                'total_players_max',
            ]);
        });
    }
};
