<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('user_notification_channels', function (Blueprint $table) {
            if (!Schema::hasColumn('user_notification_channels', 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->after('is_verified');
            }
        });

        Schema::table('channel_bind_requests', function (Blueprint $table) {
            if (Schema::hasColumn('channel_bind_requests', 'channel_id')) {
                $table->dropConstrainedForeignId('channel_id');
            }
        });

        Schema::table('event_channel_messages', function (Blueprint $table) {
            $table->index(
                ['event_id', 'occurrence_id', 'notification_type'],
                'ecm_event_occ_type_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('event_channel_messages', function (Blueprint $table) {
            $table->dropIndex('ecm_event_occ_type_idx');
        });

        Schema::table('channel_bind_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('channel_bind_requests', 'channel_id')) {
                $table->foreignId('channel_id')
                    ->nullable()
                    ->constrained('user_notification_channels')
                    ->nullOnDelete();
            }
        });

        Schema::table('user_notification_channels', function (Blueprint $table) {
            if (Schema::hasColumn('user_notification_channels', 'verified_at')) {
                $table->dropColumn('verified_at');
            }
        });
    }
};
