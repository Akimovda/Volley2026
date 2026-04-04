<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->unsignedTinyInteger('child_age_min')->nullable()->after('with_minors');
            $table->unsignedTinyInteger('child_age_max')->nullable()->after('child_age_min');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['child_age_min', 'child_age_max']);
        });
    }
};
