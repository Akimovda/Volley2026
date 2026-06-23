<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('event_occurrences', 'is_individually_edited')) {
            Schema::table('event_occurrences', function (Blueprint $table) {
                $table->boolean('is_individually_edited')->default(false)->after('is_cancelled');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('event_occurrences', 'is_individually_edited')) {
            Schema::table('event_occurrences', function (Blueprint $table) {
                $table->dropColumn('is_individually_edited');
            });
        }
    }
};
