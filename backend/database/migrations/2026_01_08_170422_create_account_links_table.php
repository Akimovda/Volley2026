<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_links', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('provider'); // 'vk' | 'telegram'
            $table->string('provider_user_id'); // telegram_id или vk_id (как строка)
            $table->string('provider_username')->nullable(); // telegram_username (если есть)
            $table->string('provider_email')->nullable(); // vk_email (если есть)

            $table->timestamps();

            // один provider_user_id может принадлежать только одному пользователю
            $table->unique(['provider', 'provider_user_id']);
            $table->index(['user_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_links');
    }
};
