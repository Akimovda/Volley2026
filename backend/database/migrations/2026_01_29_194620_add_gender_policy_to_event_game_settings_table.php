<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('event_game_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('event_game_settings', 'gender_policy')) {
                $table->string('gender_policy', 32)->nullable()->index();
            }
            if (!Schema::hasColumn('event_game_settings', 'gender_limited_side')) {
                $table->string('gender_limited_side', 16)->nullable();
            }
            if (!Schema::hasColumn('event_game_settings', 'gender_limited_max')) {
                $table->integer('gender_limited_max')->nullable();
            }
            if (!Schema::hasColumn('event_game_settings', 'gender_limited_positions')) {
                $table->jsonb('gender_limited_positions')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('event_game_settings', function (Blueprint $table) {
            if (Schema::hasColumn('event_game_settings', 'gender_policy')) {
                $table->dropIndex(['gender_policy']);
                $table->dropColumn('gender_policy');
            }
            if (Schema::hasColumn('event_game_settings', 'gender_limited_side')) {
                $table->dropColumn('gender_limited_side');
            }
            if (Schema::hasColumn('event_game_settings', 'gender_limited_max')) {
                $table->dropColumn('gender_limited_max');
            }
            if (Schema::hasColumn('event_game_settings', 'gender_limited_positions')) {
                $table->dropColumn('gender_limited_positions');
            }
        });
    }
};
