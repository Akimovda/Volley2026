<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Классика (только игры, подтип 4x2): запись игроков без амплуа, как в пляжке —
        // один общий слот 'player' вместо setter/outside.
        Schema::table('event_game_settings', function (Blueprint $table) {
            $table->boolean('registration_without_positions')->default(false)->after('reserve_players_max');
        });
    }

    public function down(): void
    {
        Schema::table('event_game_settings', function (Blueprint $table) {
            $table->dropColumn('registration_without_positions');
        });
    }
};
