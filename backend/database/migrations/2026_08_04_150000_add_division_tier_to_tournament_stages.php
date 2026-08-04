<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_stages', function (Blueprint $table) {
            // 1 = самый сильный дивизион (текущий "Hard"), возрастание номера = слабее.
            // Не enum — источник истины для группировки дивизионов вместо паттерна
            // по имени стадии (str_starts_with 'Группа ' + str_contains Hard/Medium/Lite).
            $table->smallInteger('division_tier')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('tournament_stages', function (Blueprint $table) {
            $table->dropColumn('division_tier');
        });
    }
};
