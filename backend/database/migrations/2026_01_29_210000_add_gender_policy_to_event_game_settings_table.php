<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_game_settings', function (Blueprint $table) {
            // 1) gender_policy: only_male | only_female | mixed_open | mixed_limited
            if (!Schema::hasColumn('event_game_settings', 'gender_policy')) {
                $table->string('gender_policy', 32)->nullable()->after('positions');
                $table->index(['gender_policy']);
            }

            // 2) gender_limited_side: male | female
            if (!Schema::hasColumn('event_game_settings', 'gender_limited_side')) {
                $table->string('gender_limited_side', 16)->nullable()->after('gender_policy');
                $table->index(['gender_limited_side']);
            }

            // 3) gender_limited_max: int
            if (!Schema::hasColumn('event_game_settings', 'gender_limited_max')) {
                $table->unsignedSmallInteger('gender_limited_max')->nullable()->after('gender_limited_side');
            }

            // 4) gender_limited_positions: jsonb array<string>
            if (!Schema::hasColumn('event_game_settings', 'gender_limited_positions')) {
                $table->jsonb('gender_limited_positions')->nullable()->after('gender_limited_max');
            }
        });
    }

    public function down(): void
    {
        Schema::table('event_game_settings', function (Blueprint $table) {
            if (Schema::hasColumn('event_game_settings', 'gender_limited_positions')) {
                $table->dropColumn('gender_limited_positions');
            }
            if (Schema::hasColumn('event_game_settings', 'gender_limited_max')) {
                $table->dropColumn('gender_limited_max');
            }
            if (Schema::hasColumn('event_game_settings', 'gender_limited_side')) {
                try { $table->dropIndex(['gender_limited_side']); } catch (\Throwable $e) {}
                $table->dropColumn('gender_limited_side');
            }
            if (Schema::hasColumn('event_game_settings', 'gender_policy')) {
                try { $table->dropIndex(['gender_policy']); } catch (\Throwable $e) {}
                $table->dropColumn('gender_policy');
            }
        });
    }
};
