<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->timestamp('cancel_self_until_waitlist')->nullable()->after('cancel_self_until');
        });

        Schema::table('event_occurrences', function (Blueprint $table) {
            $table->timestamp('cancel_self_until_waitlist')->nullable()->after('cancel_self_until');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('cancel_self_until_waitlist');
        });

        Schema::table('event_occurrences', function (Blueprint $table) {
            $table->dropColumn('cancel_self_until_waitlist');
        });
    }
};
