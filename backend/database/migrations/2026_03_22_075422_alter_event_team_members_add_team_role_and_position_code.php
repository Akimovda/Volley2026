<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_team_members', function (Blueprint $table) {
            $table->string('team_role', 32)->nullable()->after('role_code');
            $table->string('position_code', 32)->nullable()->after('team_role');

            $table->index(['event_team_id', 'team_role']);
            $table->index(['event_team_id', 'position_code']);
        });
    }

    public function down(): void
    {
        Schema::table('event_team_members', function (Blueprint $table) {
            $table->dropIndex(['event_team_id', 'team_role']);
            $table->dropIndex(['event_team_id', 'position_code']);

            $table->dropColumn(['team_role', 'position_code']);
        });
    }
};
