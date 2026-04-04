<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('title', 500)->nullable();
            $table->text('body')->nullable();
            $table->string('image_url', 2048)->nullable();
            $table->string('button_text', 255)->nullable();
            $table->string('button_url', 2048)->nullable();
            $table->json('filters_json')->nullable();
            $table->json('channels_json')->nullable();
            $table->string('status', 32)->default('draft'); // draft|scheduled|processing|sent|failed|cancelled
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['status']);
            $table->index(['scheduled_at']);
            $table->index(['created_by']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcasts');
    }
};
