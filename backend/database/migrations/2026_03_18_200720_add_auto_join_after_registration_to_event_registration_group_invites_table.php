<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_registration_group_invites', function (Blueprint $table) {
            $table->boolean('auto_join_after_registration')
                ->default(false)
                ->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('event_registration_group_invites', function (Blueprint $table) {
            $table->dropColumn('auto_join_after_registration');
        });
    }
};
