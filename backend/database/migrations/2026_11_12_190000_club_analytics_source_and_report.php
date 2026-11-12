<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Источник брони для аналитики: player (игрок через сайт) / manual (внесена управляющим) / event (мероприятие)
        Schema::table('court_bookings', function (Blueprint $table) {
            $table->string('source', 16)->nullable()->after('payment_mode');
        });
        // Историю размечаем только там, где признак однозначен
        DB::table('court_bookings')->whereNotNull('event_id')->update(['source' => 'event']);
        DB::table('court_bookings')->whereNull('event_id')->whereNotNull('guest_name')->update(['source' => 'manual']);

        // Ежемесячный отчёт по аналитике клуба (Организатор Pro) — по согласию владельца
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('club_report_monthly')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('club_report_monthly'));
        Schema::table('court_bookings', fn (Blueprint $t) => $t->dropColumn('source'));
    }
};
