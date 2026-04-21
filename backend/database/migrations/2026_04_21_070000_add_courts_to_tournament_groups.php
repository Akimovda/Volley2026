<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_groups', function (Blueprint $table) {
            $table->json('courts')->nullable()->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('tournament_groups', function (Blueprint $table) {
            $table->dropColumn('courts');
        });
    }
};
