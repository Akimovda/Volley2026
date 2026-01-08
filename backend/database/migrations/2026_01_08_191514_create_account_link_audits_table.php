<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_link_audits', function (Blueprint $table) {
            $table->id();

            // основной аккаунт (куда добавили способ входа)
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // аккаунт, которым пришли и “отдали” провайдера (опционально)
            $table->foreignId('linked_from_user_id')->nullable()->constrained('users')->nullOnDelete();

            // какой провайдер привязали: 'vk' | 'telegram'
            $table->string('provider', 32);

            // provider id (vk_id / telegram_id) — строкой
            $table->string('provider_user_id', 128)->nullable();

            // метод: link_code / etc
            $table->string('method', 32)->default('link_code');

            // какой код использовали (если привязка через link_code)
            $table->foreignId('link_code_id')->nullable()->constrained('account_link_codes')->nullOnDelete();

            $table->string('ip', 64)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->timestamps();

            $table->index(['user_id', 'provider']);
            $table->index(['linked_from_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_link_audits');
    }
};
