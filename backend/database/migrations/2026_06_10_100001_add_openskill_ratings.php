<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_career_stats', function (Blueprint $table) {
            $table->decimal('mu', 6, 3)->default(25.000)->after('elo_rating');
            $table->decimal('sigma', 6, 3)->default(8.333)->after('mu');
        });

        Schema::table('tournament_season_stats', function (Blueprint $table) {
            $table->decimal('mu_season', 6, 3)->default(25.000)->after('elo_season');
            $table->decimal('sigma_season', 6, 3)->default(8.333)->after('mu_season');
        });
    }

    public function down(): void
    {
        Schema::table('player_career_stats', function (Blueprint $table) {
            $table->dropColumn(['mu', 'sigma']);
        });

        Schema::table('tournament_season_stats', function (Blueprint $table) {
            $table->dropColumn(['mu_season', 'sigma_season']);
        });
    }
};
