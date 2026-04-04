<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_notification_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('platform', 32); // telegram | vk | max
            $table->string('title')->nullable();
            $table->string('chat_id', 191);
            $table->boolean('is_verified')->default(false);

            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'platform', 'chat_id'], 'uniq_user_platform_chat');
            $table->index(['user_id', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notification_channels');
    }
};
