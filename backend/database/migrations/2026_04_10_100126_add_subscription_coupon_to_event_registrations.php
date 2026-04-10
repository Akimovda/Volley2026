<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('event_registrations', function (Blueprint $table) {
            // Абонемент
            $table->unsignedBigInteger('subscription_id')->nullable()->after('payment_expires_at');
            $table->unsignedBigInteger('subscription_usage_id')->nullable()->after('subscription_id');

            // Купон
            $table->unsignedBigInteger('coupon_id')->nullable()->after('subscription_usage_id');
            $table->unsignedTinyInteger('coupon_discount_pct')->nullable()->after('coupon_id');
        });
    }

    public function down(): void {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropColumn([
                'subscription_id', 'subscription_usage_id',
                'coupon_id', 'coupon_discount_pct',
            ]);
        });
    }
};
