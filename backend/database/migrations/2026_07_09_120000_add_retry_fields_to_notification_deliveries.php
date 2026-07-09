<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('notification_deliveries', function (Blueprint $table) {
            $table->unsignedTinyInteger('attempts')->default(0)->after('error');
            $table->timestampTz('next_retry_at')->nullable()->after('attempts');
            // null = ещё не классифицировано (in_app и старые записи до миграции), true = транзиент, false = постоянная
            $table->boolean('is_retryable')->nullable()->after('next_retry_at');

            $table->index(['status', 'is_retryable', 'next_retry_at']);
        });
    }

    public function down(): void
    {
        Schema::table('notification_deliveries', function (Blueprint $table) {
            $table->dropIndex(['status', 'is_retryable', 'next_retry_at']);
            $table->dropColumn(['attempts', 'next_retry_at', 'is_retryable']);
        });
    }
};
