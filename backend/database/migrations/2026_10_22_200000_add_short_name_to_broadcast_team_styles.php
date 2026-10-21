<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('broadcast_team_styles', function (Blueprint $t) {
            $t->string('short_name', 4)->nullable()->after('color');
        });
    }

    public function down(): void
    {
        Schema::table('broadcast_team_styles', function (Blueprint $t) {
            $t->dropColumn('short_name');
        });
    }
};
