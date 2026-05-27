<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_notification_channels', function (Blueprint $table) {
            $table->unsignedBigInteger('message_thread_id')->nullable()->after('include_registered_list');
        });
    }

    public function down(): void
    {
        Schema::table('event_notification_channels', function (Blueprint $table) {
            $table->dropColumn('message_thread_id');
        });
    }
};
