<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('events', function (Blueprint $table) {
            $table->string('payment_method', 20)->nullable()->after('price_currency');
            // cash | tbank_link | sber_link | yoomoney | wallet
            $table->string('payment_link')->nullable()->after('payment_method');
            // Политика возврата для конкретного мероприятия
            $table->unsignedSmallInteger('refund_hours_full')->nullable()->after('payment_link');
            $table->unsignedSmallInteger('refund_hours_partial')->nullable()->after('refund_hours_full');
            $table->unsignedTinyInteger('refund_partial_pct')->nullable()->after('refund_hours_partial');
        });
    }
    public function down(): void {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['payment_method','payment_link','refund_hours_full','refund_hours_partial','refund_partial_pct']);
        });
    }
};
