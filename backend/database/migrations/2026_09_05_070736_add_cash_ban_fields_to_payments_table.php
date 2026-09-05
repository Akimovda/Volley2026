<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->timestamp('cash_ban_deadline_at')->nullable()->after('org_confirmed_at');
            $table->timestamp('cash_banned_at')->nullable()->after('cash_ban_deadline_at');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['cash_ban_deadline_at', 'cash_banned_at']);
        });
    }
};
