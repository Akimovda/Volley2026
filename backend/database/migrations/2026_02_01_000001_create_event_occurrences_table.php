<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('event_occurrences', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();

            // Время храним в UTC как и events.starts_at/ends_at
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at')->nullable();

            // Локальный TZ фиксируем на всякий случай
            $table->string('timezone', 64)->default('UTC');

            // Для “отмены одного занятия” без удаления
            $table->boolean('is_cancelled')->default(false);
            $table->dateTime('cancelled_at')->nullable();

            // Чтобы однозначно не плодить дубликаты при генерации
            $table->string('uniq_key', 120)->unique();

            $table->timestamps();

            $table->index(['event_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_occurrences');
    }
};
