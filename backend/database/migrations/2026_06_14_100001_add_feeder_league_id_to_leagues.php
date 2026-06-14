<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leagues', function (Blueprint $table) {
            $table->foreignId('feeder_league_id')
                ->nullable()
                ->after('status')
                ->constrained('leagues')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leagues', function (Blueprint $table) {
            $table->dropForeign(['feeder_league_id']);
            $table->dropColumn('feeder_league_id');
        });
    }
};
