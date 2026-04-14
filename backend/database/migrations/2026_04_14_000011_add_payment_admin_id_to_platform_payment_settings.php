<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_payment_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('payment_admin_id')->default(1)->after('ad_event_price_rub');
        });
    }

    public function down(): void
    {
        Schema::table('platform_payment_settings', function (Blueprint $table) {
            $table->dropColumn('payment_admin_id');
        });
    }
};
