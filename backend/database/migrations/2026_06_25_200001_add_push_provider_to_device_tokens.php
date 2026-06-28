<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_tokens', function (Blueprint $table) {
            $table->string('push_provider', 16)->nullable()->after('platform');
        });

        DB::table('device_tokens')
            ->where('platform', 'ios')
            ->update(['push_provider' => 'apns']);

        Schema::table('device_tokens', function (Blueprint $table) {
            $table->string('push_provider', 16)->default('apns')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('device_tokens', function (Blueprint $table) {
            $table->dropColumn('push_provider');
        });
    }
};
