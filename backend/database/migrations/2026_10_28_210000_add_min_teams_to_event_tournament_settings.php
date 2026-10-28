<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Минимум команд для проведения командного турнира. NULL = порога нет, авто-отмена по кворуму не применяется
        // (tournament_teams_count — это вместимость, а не минимум).
        Schema::table('event_tournament_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('min_teams')->nullable()->after('teams_count');
        });
    }

    public function down(): void
    {
        Schema::table('event_tournament_settings', function (Blueprint $table) {
            $table->dropColumn('min_teams');
        });
    }
};
