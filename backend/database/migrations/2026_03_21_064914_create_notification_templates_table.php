<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('name', 255);
            $table->string('channel', 32)->nullable(); // null = общий шаблон
            $table->string('title_template', 500)->nullable();
            $table->text('body_template')->nullable();
            $table->string('image_url', 2048)->nullable();
            $table->string('button_text', 255)->nullable();
            $table->string('button_url_template', 2048)->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['code', 'channel']);
            $table->index(['is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
