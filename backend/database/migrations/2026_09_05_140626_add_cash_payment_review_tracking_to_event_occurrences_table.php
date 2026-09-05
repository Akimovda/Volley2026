<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('event_occurrences', function (Blueprint $table) {
            $table->timestampTz('cash_payment_reviewed_at')->nullable()->after('payment_link');
            $table->timestampTz('cash_payment_autoprocessed_at')->nullable()->after('cash_payment_reviewed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_occurrences', function (Blueprint $table) {
            $table->dropColumn(['cash_payment_reviewed_at', 'cash_payment_autoprocessed_at']);
        });
    }
};
