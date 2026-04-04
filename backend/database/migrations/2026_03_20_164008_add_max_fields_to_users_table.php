<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'max_chat_id')) {
                $table->string('max_chat_id')->nullable()->index();
            }

            if (!Schema::hasColumn('users', 'max_linked_at')) {
                $table->timestamp('max_linked_at')->nullable();
            }

            if (!Schema::hasColumn('users', 'max_notifications_enabled')) {
                $table->boolean('max_notifications_enabled')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'max_notifications_enabled')) {
                $table->dropColumn('max_notifications_enabled');
            }

            if (Schema::hasColumn('users', 'max_linked_at')) {
                $table->dropColumn('max_linked_at');
            }

            if (Schema::hasColumn('users', 'max_chat_id')) {
                $table->dropColumn('max_chat_id');
            }
        });
    }
};
