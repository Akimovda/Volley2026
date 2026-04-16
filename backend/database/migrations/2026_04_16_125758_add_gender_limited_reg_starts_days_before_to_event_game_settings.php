<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('event_game_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('event_game_settings', 'gender_limited_reg_starts_days_before')) {
                $table->smallInteger('gender_limited_reg_starts_days_before')
                    ->nullable()
                    ->after('gender_limited_positions');
            }
        });
    }

    public function down(): void
    {
        Schema::table('event_game_settings', function (Blueprint $table) {
            if (Schema::hasColumn('event_game_settings', 'gender_limited_reg_starts_days_before')) {
                $table->dropColumn('gender_limited_reg_starts_days_before');
            }
        });
    }
};
