<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_notification_channels', function (Blueprint $table) {
            $table->string('bot_type')->default('system')->after('verified_at');
            $table->text('user_bot_token')->nullable()->after('bot_type');
            $table->string('user_bot_username', 64)->nullable()->after('user_bot_token');
            $table->timestamp('user_bot_verified_at')->nullable()->after('user_bot_username');
        });
    }

    public function down(): void
    {
        Schema::table('user_notification_channels', function (Blueprint $table) {
            $table->dropColumn([
                'bot_type',
                'user_bot_token',
                'user_bot_username',
                'user_bot_verified_at',
            ]);
        });
    }
};
