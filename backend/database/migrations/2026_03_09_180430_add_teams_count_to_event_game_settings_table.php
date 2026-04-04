<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_game_settings', function (Blueprint $table) {

            $table->integer('teams_count')
                ->default(2)
                ->after('subtype');

        });
    }

    public function down(): void
    {
        Schema::table('event_game_settings', function (Blueprint $table) {

            $table->dropColumn('teams_count');

        });
    }
};
