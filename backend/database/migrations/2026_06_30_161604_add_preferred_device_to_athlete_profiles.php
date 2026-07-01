<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('athlete_profiles', function (Blueprint $table) {
            $table->string('preferred_device_type')->nullable()->after('jump_height_coeff');
            $table->unsignedBigInteger('preferred_device_id')->nullable()->after('preferred_device_type');
            $table->foreign('preferred_device_id')->references('id')->on('athlete_devices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('athlete_profiles', function (Blueprint $table) {
            $table->dropForeign(['preferred_device_id']);
            $table->dropColumn(['preferred_device_type', 'preferred_device_id']);
        });
    }
};
