<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_pair_stats', function (Blueprint $table) {
            $table->string('direction', 20)->default('beach')->after('player2_id');
            $table->string('game_scheme', 20)->nullable()->after('direction');
        });
    }

    public function down(): void
    {
        Schema::table('player_pair_stats', function (Blueprint $table) {
            $table->dropColumn(['direction', 'game_scheme']);
        });
    }
};
