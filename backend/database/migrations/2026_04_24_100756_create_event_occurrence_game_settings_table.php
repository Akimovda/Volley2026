<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Таблица игровых настроек per-occurrence (override для event_game_settings).
     *
     * Семантика:
     * - Запись отсутствует = occurrence использует настройки из event_game_settings
     * - Запись есть, поле NULL = это конкретное поле наследуется от event_game_settings
     * - Запись есть, поле с значением = override для этой даты
     *
     * Зеркалит структуру event_game_settings, все поля (кроме FK) nullable.
     */
    public function up(): void
    {
        Schema::create('event_occurrence_game_settings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('occurrence_id')
                  ->constrained('event_occurrences')
                  ->cascadeOnDelete();

            // ===== Базовые игровые настройки =====
            $table->string('subtype', 10)->nullable();          // 6x6, 5x1, 4x4, 4x2, 3x3, 2x2
            $table->string('libero_mode', 20)->nullable();       // для classic 5x1
            $table->smallInteger('min_players')->nullable();     // для отмены игры если не набрались
            $table->smallInteger('max_players')->nullable();     // расчётное, обычно auto
            $table->integer('teams_count')->nullable();          // кол-во команд для турниров
            $table->jsonb('positions')->nullable();              // позиции (JSONB)

            // ===== Гендерные настройки =====
            $table->boolean('allow_girls')->nullable();
            $table->smallInteger('girls_max')->nullable();
            $table->string('gender_policy', 32)->nullable();
            $table->string('gender_limited_side', 16)->nullable();
            $table->integer('gender_limited_max')->nullable();
            $table->jsonb('gender_limited_positions')->nullable();
            $table->smallInteger('gender_limited_reg_starts_days_before')->nullable();

            $table->timestamps();

            // Уникальность по occurrence_id (1:1)
            $table->unique('occurrence_id');

            // Индексы для частых фильтров
            $table->index('subtype');
            $table->index('gender_policy');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_occurrence_game_settings');
    }
};
