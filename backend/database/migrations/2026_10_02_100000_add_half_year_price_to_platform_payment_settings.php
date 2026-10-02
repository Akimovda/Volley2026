<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_payment_settings', function (Blueprint $table) {
            $table->integer('organizer_pro_half_rub')->default(3490)->after('organizer_pro_quarter_rub');
        });

        DB::table('platform_payment_settings')->update([
            'organizer_pro_half_rub' => 3490,
            'organizer_pro_year_rub' => 4990,
        ]);
    }

    public function down(): void
    {
        Schema::table('platform_payment_settings', function (Blueprint $table) {
            $table->dropColumn('organizer_pro_half_rub');
        });
    }
};
