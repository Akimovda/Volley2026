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
        Schema::table('activity_sessions', function (Blueprint $table) {
            $table->unsignedInteger('jump_count_expected')->nullable()->after('steps');
            $table->integer('jump_count_mismatch')->nullable()->after('jump_count_expected');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_sessions', function (Blueprint $table) {
            $table->dropColumn(['jump_count_expected', 'jump_count_mismatch']);
        });
    }
};
