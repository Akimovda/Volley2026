<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->unsignedBigInteger('season_id')->nullable()->after('organizer_id');

            $table->foreign('season_id')
                  ->references('id')
                  ->on('tournament_seasons')
                  ->nullOnDelete();

            $table->index('season_id');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['season_id']);
            $table->dropIndex(['season_id']);
            $table->dropColumn('season_id');
        });
    }
};
