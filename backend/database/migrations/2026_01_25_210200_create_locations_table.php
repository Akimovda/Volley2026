<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('locations')) {
            return;
        }

        Schema::create('locations', function (Blueprint $table) {
            $table->id();

            // null = общая локация (видна всем organizer/staff)
            $table->foreignId('organizer_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('name', 255);
            $table->string('address', 500)->nullable();
            $table->string('timezone', 64); // например: Europe/Berlin

            $table->timestamps();

            $table->index(['organizer_id']);
            $table->index(['name']);
        });
    }

    public function down(): void
    {
        // Это миграция-дубликат (у тебя уже есть create_locations_table).
        // Чтобы rollback случайно не снёс рабочую таблицу — НЕ трогаем.
        // Schema::dropIfExists('locations');
    }
};
