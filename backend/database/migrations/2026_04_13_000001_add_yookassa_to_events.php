<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('ad_yookassa_payment_id', 64)->nullable()->after('ad_price_rub');
            $table->string('ad_yookassa_payment_url', 512)->nullable()->after('ad_yookassa_payment_id');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['ad_yookassa_payment_id', 'ad_yookassa_payment_url']);
        });
    }
};
