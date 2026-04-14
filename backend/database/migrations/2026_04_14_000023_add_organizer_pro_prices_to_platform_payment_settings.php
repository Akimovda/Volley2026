<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_payment_settings', function (Blueprint $table) {
            $table->integer('organizer_pro_trial_days')->default(7)->after('ad_event_price_rub');
            $table->integer('organizer_pro_month_rub')->default(499)->after('organizer_pro_trial_days');
            $table->integer('organizer_pro_quarter_rub')->default(1199)->after('organizer_pro_month_rub');
            $table->integer('organizer_pro_year_rub')->default(3999)->after('organizer_pro_quarter_rub');
        });
    }

    public function down(): void
    {
        Schema::table('platform_payment_settings', function (Blueprint $table) {
            $table->dropColumn([
                'organizer_pro_trial_days',
                'organizer_pro_month_rub',
                'organizer_pro_quarter_rub',
                'organizer_pro_year_rub',
            ]);
        });
    }
};
