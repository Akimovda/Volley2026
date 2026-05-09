<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('event_tournament_settings', function (Blueprint $table) {
            $table->boolean('allow_incomplete_application')
                ->default(false)
                ->after('auto_submit_when_ready');
        });
    }

    public function down(): void
    {
        Schema::table('event_tournament_settings', function (Blueprint $table) {
            $table->dropColumn('allow_incomplete_application');
        });
    }
};
