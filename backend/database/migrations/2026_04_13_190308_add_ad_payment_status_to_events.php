<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('ad_payment_status')->nullable()->after('allow_registration');
            // pending = ожидает оплаты, paid = оплачено, expired = истекло
            $table->timestamp('ad_payment_expires_at')->nullable()->after('ad_payment_status');
            $table->unsignedInteger('ad_price_rub')->nullable()->after('ad_payment_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['ad_payment_status', 'ad_payment_expires_at', 'ad_price_rub']);
        });
    }
};
