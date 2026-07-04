<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('court_price_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('direction_id')
                ->constrained('location_directions')->cascadeOnDelete();
            $table->foreignId('court_id')->nullable()
                ->constrained('location_courts')->cascadeOnDelete();
            // null = все корты направления
            $table->unsignedTinyInteger('day_of_week')->nullable();
            // null = все дни
            $table->time('starts_at')->nullable(); // null = весь день
            $table->time('ends_at')->nullable();
            $table->decimal('price_per_hour', 8, 2);
            $table->unsignedTinyInteger('priority')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('court_price_rules');
    }
};
