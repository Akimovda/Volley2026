<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_sessions', function (Blueprint $table) {
            $table->integer('jump_count')->default(0)->after('samples_count');
            $table->decimal('jump_avg_height_cm', 5, 1)->nullable()->after('jump_count');
            $table->decimal('jump_max_height_cm', 5, 1)->nullable()->after('jump_avg_height_cm');
            $table->jsonb('tracked_capabilities')->nullable()->after('jump_max_height_cm');
        });
    }

    public function down(): void
    {
        Schema::table('activity_sessions', function (Blueprint $table) {
            $table->dropColumn(['jump_count', 'jump_avg_height_cm', 'jump_max_height_cm', 'tracked_capabilities']);
        });
    }
};
