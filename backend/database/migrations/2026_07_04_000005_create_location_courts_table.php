<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_courts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('direction_id')
                ->constrained('location_directions')->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_courts');
    }
};
