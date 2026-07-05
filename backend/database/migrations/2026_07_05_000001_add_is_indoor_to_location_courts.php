<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('location_courts', function (Blueprint $table) {
            $table->boolean('is_indoor')->default(false)->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('location_courts', function (Blueprint $table) {
            $table->dropColumn('is_indoor');
        });
    }
};
