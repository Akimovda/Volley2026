<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Номер на футболке игрока на уровне «событие + игрок» (турнир / игровой день): задаётся на экране
        // стартовой расстановки, сохраняется на весь турнир. Дубли номеров допустимы (уникальна только пара событие+игрок).
        Schema::create('event_jersey_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('number')->nullable();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('updated_by_link_id')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'user_id'], 'ejn_event_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_jersey_numbers');
    }
};
