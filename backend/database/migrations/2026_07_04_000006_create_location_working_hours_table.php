<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_working_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('direction_id')
                ->constrained('location_directions')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 0=Пн ... 6=Вс
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->boolean('is_day_off')->default(false);
            $table->timestamps();

            $table->unique(['direction_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_working_hours');
    }
};
