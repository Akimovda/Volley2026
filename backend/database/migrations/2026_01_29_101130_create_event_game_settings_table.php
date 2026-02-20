<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('event_game_settings', function (Blueprint $table) {
            $table->id();

            // 1:1 с events
            $table->foreignId('event_id')
                ->constrained('events')
                ->cascadeOnDelete()
                ->unique();

            // classic/game subtype: 4x4 / 4x2 / 5x1
            $table->string('subtype', 10); // '4x4','4x2','5x1'

            // вместо game_has_libero: режим либеро (потом под это UI)
            // 'with_libero' | 'without_libero'
            $table->string('libero_mode', 20)->nullable();

            $table->unsignedSmallInteger('min_players')->nullable();
            $table->unsignedSmallInteger('max_players')->nullable();

            $table->boolean('allow_girls')->default(false);
            $table->unsignedSmallInteger('girls_max')->nullable();

            // позиции регистрации: Postgres jsonb (Laravel ->jsonb)
            $table->jsonb('positions')->nullable();

            $table->timestamps();

            // быстрые фильтры
            $table->index(['subtype']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_game_settings');
    }
};
