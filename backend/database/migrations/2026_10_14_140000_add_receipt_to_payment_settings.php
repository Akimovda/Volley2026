<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_settings', function (Blueprint $t) {
            $t->boolean('yookassa_receipt_enabled')->default(false);
            $t->unsignedSmallInteger('yookassa_vat_code')->default(1);
            $t->unsignedSmallInteger('yookassa_tax_system_code')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payment_settings', fn (Blueprint $t) => $t->dropColumn(['yookassa_receipt_enabled', 'yookassa_vat_code', 'yookassa_tax_system_code']));
    }
};
