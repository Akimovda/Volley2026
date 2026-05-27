<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('event_teams', function (Blueprint $table) {
            $table->unsignedSmallInteger('reserve_position')->nullable()->after('status');
            $table->string('confirmation_token', 64)->nullable()->unique()->after('reserve_position');
            $table->timestamp('confirmation_expires_at')->nullable()->after('confirmation_token');
        });
    }

    public function down(): void
    {
        Schema::table('event_teams', function (Blueprint $table) {
            $table->dropColumn(['reserve_position', 'confirmation_token', 'confirmation_expires_at']);
        });
    }
};
