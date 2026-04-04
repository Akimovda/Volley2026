<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'telegram_notify_chat_id')) {
                $table->string('telegram_notify_chat_id')->nullable()->index();
            }

            if (!Schema::hasColumn('users', 'telegram_notify_linked_at')) {
                $table->timestamp('telegram_notify_linked_at')->nullable();
            }

            if (!Schema::hasColumn('users', 'vk_notify_user_id')) {
                $table->string('vk_notify_user_id')->nullable()->index();
            }

            if (!Schema::hasColumn('users', 'vk_notify_linked_at')) {
                $table->timestamp('vk_notify_linked_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $drops = [];

            if (Schema::hasColumn('users', 'telegram_notify_chat_id')) {
                $drops[] = 'telegram_notify_chat_id';
            }

            if (Schema::hasColumn('users', 'telegram_notify_linked_at')) {
                $drops[] = 'telegram_notify_linked_at';
            }

            if (Schema::hasColumn('users', 'vk_notify_user_id')) {
                $drops[] = 'vk_notify_user_id';
            }

            if (Schema::hasColumn('users', 'vk_notify_linked_at')) {
                $drops[] = 'vk_notify_linked_at';
            }

            if (!empty($drops)) {
                $table->dropColumn($drops);
            }
        });
    }
};
