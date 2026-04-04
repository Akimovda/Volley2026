<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcast_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('broadcast_id')->constrained('broadcasts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('user_notification_id')->nullable();
            $table->string('status', 32)->default('pending'); // pending|created|sent|failed|skipped
            $table->text('error')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['broadcast_id', 'user_id']);
            $table->index(['status']);
            $table->index(['user_notification_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_recipients');
    }
};
