<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('channel_bind_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('platform', 32); // telegram | vk | max
            $table->string('token', 128)->unique();

            $table->string('status', 32)->default('pending'); // pending | completed | expired
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('channel_id')->nullable()->constrained('user_notification_channels')->nullOnDelete();

            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'platform']);
            $table->index(['platform', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_bind_requests');
    }
};
