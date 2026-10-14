<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_payment_settings', function (Blueprint $table) {
            // По умолчанию блок receipt в платёж НЕ отправляется — чеки формирует ЮKassa (решение владельца).
            $table->boolean('yookassa_receipt_enabled')->default(false);
            $table->unsignedSmallInteger('yookassa_vat_code')->default(1);
            $table->unsignedSmallInteger('yookassa_tax_system_code')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('platform_payment_settings', function (Blueprint $table) {
            $table->dropColumn(['yookassa_receipt_enabled', 'yookassa_vat_code', 'yookassa_tax_system_code']);
        });
    }
};
