<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (!Schema::hasColumn('events','age_policy')) {
                $table->string('age_policy', 16)->default('any'); // adult|child|any
            }
            if (!Schema::hasColumn('events','is_snow')) {
                $table->boolean('is_snow')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (Schema::hasColumn('events','age_policy')) $table->dropColumn('age_policy');
            if (Schema::hasColumn('events','is_snow')) $table->dropColumn('is_snow');
        });
    }
};
