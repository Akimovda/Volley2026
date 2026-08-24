<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->foreignId('premium_auto_booking_id')->nullable()->after('auto_booked')
                ->constrained('premium_auto_bookings')->nullOnDelete();
            $table->timestamp('premium_auto_confirm_deadline_at')->nullable()->after('premium_auto_booking_id');
        });
    }

    public function down(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropColumn('premium_auto_confirm_deadline_at');
            $table->dropConstrainedForeignId('premium_auto_booking_id');
        });
    }
};
