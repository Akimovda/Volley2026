<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('athlete_profiles', function (Blueprint $table) {
            $table->decimal('jump_height_coeff', 4, 3)->nullable()->after('reach_beach_cm');
        });
    }

    public function down(): void
    {
        Schema::table('athlete_profiles', function (Blueprint $table) {
            $table->dropColumn('jump_height_coeff');
        });
    }
};
