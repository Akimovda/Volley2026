<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            if (!Schema::hasColumn('event_registrations', 'status')) {
                $table->string('status', 24)->default('confirmed')->index();
            }
            if (!Schema::hasColumn('event_registrations', 'is_cancelled')) {
                $table->boolean('is_cancelled')->default(false)->index();
            }
            if (!Schema::hasColumn('event_registrations', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->index();
            }
        });

        // Backfill старых строк (на всякий)
        // confirmed + not cancelled
        DB::table('event_registrations')->whereNull('status')->update(['status' => 'confirmed']);
        DB::table('event_registrations')->whereNull('is_cancelled')->update(['is_cancelled' => false]);
        // cancelled_at оставляем NULL
    }

    public function down(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            if (Schema::hasColumn('event_registrations', 'cancelled_at')) {
                $table->dropColumn('cancelled_at');
            }
            if (Schema::hasColumn('event_registrations', 'is_cancelled')) {
                $table->dropColumn('is_cancelled');
            }
            if (Schema::hasColumn('event_registrations', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
