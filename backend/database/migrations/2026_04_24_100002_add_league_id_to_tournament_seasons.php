<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_seasons', function (Blueprint $table) {
            $table->foreignId('league_id')
                  ->nullable()
                  ->after('organizer_id')
                  ->constrained('leagues')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tournament_seasons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('league_id');
        });
    }
};
