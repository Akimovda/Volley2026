<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('court_bookings', function (Blueprint $table) {
            $table->timestamp('reminded_24h_at')->nullable()->after('cancel_reason');
            $table->timestamp('reminded_2h_at')->nullable()->after('reminded_24h_at');
        });
    }

    public function down(): void
    {
        Schema::table('court_bookings', function (Blueprint $table) {
            $table->dropColumn(['reminded_24h_at', 'reminded_2h_at']);
        });
    }
};
