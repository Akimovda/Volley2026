<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // {"bank_card":3.8,"sbp":2.2,...} — процент комиссии по способу оплаты; нет ключа = способ не предлагается
    public function up(): void
    {
        Schema::table('payment_settings', fn (Blueprint $t) => $t->json('fee_methods')->nullable());
        Schema::table('platform_payment_settings', function (Blueprint $t) {
            $t->json('sub_fee_methods')->nullable();
            $t->json('ad_fee_methods')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payment_settings', fn (Blueprint $t) => $t->dropColumn('fee_methods'));
        Schema::table('platform_payment_settings', fn (Blueprint $t) => $t->dropColumn(['sub_fee_methods', 'ad_fee_methods']));
    }
};
