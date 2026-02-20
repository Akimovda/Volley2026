<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('event_occurrences', function (Blueprint $table) {
            if (!Schema::hasColumn('event_occurrences','age_policy')) {
                $table->string('age_policy', 16)->nullable(); // snapshot
            }
            if (!Schema::hasColumn('event_occurrences','is_snow')) {
                $table->boolean('is_snow')->nullable(); // snapshot
            }
        });
    }

    public function down(): void
    {
        Schema::table('event_occurrences', function (Blueprint $table) {
            if (Schema::hasColumn('event_occurrences','age_policy')) $table->dropColumn('age_policy');
            if (Schema::hasColumn('event_occurrences','is_snow')) $table->dropColumn('is_snow');
        });
    }
};
