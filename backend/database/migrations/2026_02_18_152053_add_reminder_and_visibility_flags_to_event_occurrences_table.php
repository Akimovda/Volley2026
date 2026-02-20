<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('event_occurrences', function (Blueprint $table) {
            if (!Schema::hasColumn('event_occurrences', 'remind_registration_enabled')) {
                $table->boolean('remind_registration_enabled')->default(true)->after('cancel_self_until');
            }
            if (!Schema::hasColumn('event_occurrences', 'remind_registration_minutes_before')) {
                $table->integer('remind_registration_minutes_before')->default(600)->after('remind_registration_enabled');
            }
            if (!Schema::hasColumn('event_occurrences', 'show_participants')) {
                $table->boolean('show_participants')->default(true)->after('remind_registration_minutes_before');
            }
        });
    }

    public function down(): void
    {
        Schema::table('event_occurrences', function (Blueprint $table) {
            if (Schema::hasColumn('event_occurrences', 'show_participants')) $table->dropColumn('show_participants');
            if (Schema::hasColumn('event_occurrences', 'remind_registration_minutes_before')) $table->dropColumn('remind_registration_minutes_before');
            if (Schema::hasColumn('event_occurrences', 'remind_registration_enabled')) $table->dropColumn('remind_registration_enabled');
        });
    }
};
