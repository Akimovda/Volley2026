<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_payment_settings', function (Blueprint $table) {
            $table->integer('premium_half_rub')->default(2490)->after('organizer_pro_year_rub');
            $table->integer('premium_year_rub')->default(3990)->after('premium_half_rub');
        });
    }

    public function down(): void
    {
        Schema::table('platform_payment_settings', function (Blueprint $table) {
            $table->dropColumn(['premium_half_rub', 'premium_year_rub']);
        });
    }
};
