<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizer_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('organizer_requests', 'user_id')) {
                $table->foreignId('user_id')
                    ->after('id')
                    ->constrained('users')
                    ->cascadeOnDelete();
            }

            if (!Schema::hasColumn('organizer_requests', 'status')) {
                // pending | approved | rejected
                $table->string('status')->default('pending')->after('user_id');
            }

            if (!Schema::hasColumn('organizer_requests', 'message')) {
                $table->text('message')->nullable()->after('status');
            }

            if (!Schema::hasColumn('organizer_requests', 'reviewed_by')) {
                $table->foreignId('reviewed_by')
                    ->nullable()
                    ->after('message')
                    ->constrained('users');
            }

            if (!Schema::hasColumn('organizer_requests', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            }
        });

        Schema::table('organizer_requests', function (Blueprint $table) {
            // индексы
            $table->index('user_id');
            $table->index('status');

            // запретить несколько pending у одного пользователя (упрощенно)
            $table->unique(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('organizer_requests', function (Blueprint $table) {
            // сначала индексы/unique
            $table->dropUnique(['user_id', 'status']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['status']);

            // потом FK/колонки
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropConstrainedForeignId('user_id');

            $table->dropColumn(['reviewed_at', 'message', 'status']);
        });
    }
};
