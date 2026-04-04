<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_notify_bindings', function (Blueprint $table) {
            if (!Schema::hasColumn('telegram_notify_bindings', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('token');
            }

            if (!Schema::hasColumn('telegram_notify_bindings', 'used_at')) {
                $table->timestamp('used_at')->nullable()->after('telegram_chat_id');
            }

            if (!Schema::hasColumn('telegram_notify_bindings', 'raw_update')) {
                $table->jsonb('raw_update')->nullable()->after('used_at');
            }
        });

        Schema::table('vk_notify_bindings', function (Blueprint $table) {
            if (!Schema::hasColumn('vk_notify_bindings', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('token');
            }

            if (!Schema::hasColumn('vk_notify_bindings', 'used_at')) {
                $table->timestamp('used_at')->nullable()->after('vk_user_id');
            }

            if (!Schema::hasColumn('vk_notify_bindings', 'raw_update')) {
                $table->jsonb('raw_update')->nullable()->after('used_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('telegram_notify_bindings', function (Blueprint $table) {
            $drops = [];

            if (Schema::hasColumn('telegram_notify_bindings', 'expires_at')) {
                $drops[] = 'expires_at';
            }
            if (Schema::hasColumn('telegram_notify_bindings', 'used_at')) {
                $drops[] = 'used_at';
            }
            if (Schema::hasColumn('telegram_notify_bindings', 'raw_update')) {
                $drops[] = 'raw_update';
            }

            if ($drops) {
                $table->dropColumn($drops);
            }
        });

        Schema::table('vk_notify_bindings', function (Blueprint $table) {
            $drops = [];

            if (Schema::hasColumn('vk_notify_bindings', 'expires_at')) {
                $drops[] = 'expires_at';
            }
            if (Schema::hasColumn('vk_notify_bindings', 'used_at')) {
                $drops[] = 'used_at';
            }
            if (Schema::hasColumn('vk_notify_bindings', 'raw_update')) {
                $drops[] = 'raw_update';
            }

            if ($drops) {
                $table->dropColumn($drops);
            }
        });
    }
};
