<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_notification_channels')) {
            if (!Schema::hasColumn('user_notification_channels', 'verified_at')) {
                Schema::table('user_notification_channels', function (Blueprint $table) {
                    $table->timestamp('verified_at')->nullable()->after('is_verified');
                });
            }

            if (Schema::hasColumn('user_notification_channels', 'is_verified')) {
                DB::table('user_notification_channels')
                    ->where('is_verified', true)
                    ->whereNull('verified_at')
                    ->update([
                        'verified_at' => now(),
                    ]);
            }
        }

        if (
            Schema::hasTable('channel_bind_requests') &&
            Schema::hasColumn('channel_bind_requests', 'channel_id')
        ) {
            try {
                Schema::table('channel_bind_requests', function (Blueprint $table) {
                    $table->dropForeign(['channel_id']);
                });
            } catch (\Throwable $e) {
                // ignore
            }

            try {
                Schema::table('channel_bind_requests', function (Blueprint $table) {
                    $table->dropColumn('channel_id');
                });
            } catch (\Throwable $e) {
                // ignore
            }
        }

        // индекс тут больше НЕ создаём, потому что он уже существует
    }

    public function down(): void
    {
        if (
            Schema::hasTable('channel_bind_requests') &&
            !Schema::hasColumn('channel_bind_requests', 'channel_id')
        ) {
            Schema::table('channel_bind_requests', function (Blueprint $table) {
                $table->foreignId('channel_id')
                    ->nullable()
                    ->after('expires_at')
                    ->constrained('user_notification_channels')
                    ->nullOnDelete();
            });
        }

        if (
            Schema::hasTable('user_notification_channels') &&
            Schema::hasColumn('user_notification_channels', 'verified_at')
        ) {
            Schema::table('user_notification_channels', function (Blueprint $table) {
                $table->dropColumn('verified_at');
            });
        }
    }
};
