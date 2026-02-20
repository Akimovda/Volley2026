<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->smallInteger('classic_level_max')->nullable()->after('classic_level_min');
            $table->smallInteger('beach_level_max')->nullable()->after('beach_level_min');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['classic_level_max', 'beach_level_max']);
        });
    }
};
