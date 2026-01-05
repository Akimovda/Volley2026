<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('requires_personal_data')->default(false);
            $table->unsignedSmallInteger('classic_level_min')->nullable();
            $table->unsignedSmallInteger('beach_level_min')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['requires_personal_data', 'classic_level_min', 'beach_level_min']);
        });
    }
};
