<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('athlete_profiles', function (Blueprint $table) {
            $table->smallInteger('reach_classic_cm')->nullable()->after('weight_kg');
            $table->smallInteger('reach_beach_cm')->nullable()->after('reach_classic_cm');
        });
    }

    public function down(): void
    {
        Schema::table('athlete_profiles', function (Blueprint $table) {
            $table->dropColumn(['reach_classic_cm', 'reach_beach_cm']);
        });
    }
};
