<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_link_codes', function (Blueprint $table) {
            $table->id();

            // “главный” аккаунт, к которому привязываем второй провайдер
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // sha256(code)
            $table->string('code_hash', 64)->unique();

            // опционально: если захотите ограничивать, что код только для vk/telegram
            $table->string('target_provider')->nullable(); // 'vk' | 'telegram' | null

            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();

            // кем “погасили” (аккаунт, который вводил код)
            $table->foreignId('consumed_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['user_id', 'expires_at']);
            $table->index(['consumed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_link_codes');
    }
};
