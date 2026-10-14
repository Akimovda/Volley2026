<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_settings', fn (Blueprint $t) => $t->string('tax_system', 30)->nullable());
        Schema::table('platform_payment_settings', function (Blueprint $t) {
            $t->string('sub_tax_system', 30)->nullable();
            $t->string('ad_tax_system', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payment_settings', fn (Blueprint $t) => $t->dropColumn('tax_system'));
        Schema::table('platform_payment_settings', fn (Blueprint $t) => $t->dropColumn(['sub_tax_system', 'ad_tax_system']));
    }
};
