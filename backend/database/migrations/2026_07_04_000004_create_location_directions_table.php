<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_directions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->string('direction', 20); // 'classic' | 'beach'
            $table->unsignedTinyInteger('courts_count')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['location_id', 'direction']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_directions');
    }
};
