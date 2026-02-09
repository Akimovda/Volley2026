<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            if (!Schema::hasColumn('event_registrations', 'position')) {
                $table->string('position', 32)->nullable()->after('user_id');
                $table->index(['event_id', 'position']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            if (Schema::hasColumn('event_registrations', 'position')) {
                $table->dropIndex(['event_id', 'position']);
                $table->dropColumn('position');
            }
        });
    }
};
