<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_season_events', function (Blueprint $table) {
            $table->timestamp('synced_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('tournament_season_events', function (Blueprint $table) {
            $table->dropColumn('synced_at');
        });
    }
};
