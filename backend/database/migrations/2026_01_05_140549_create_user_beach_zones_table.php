<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_beach_zones', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // 2 или 4
            $table->unsignedTinyInteger('zone');

            $table->boolean('is_primary')->default(false);

            $table->timestamps();

            $table->unique(['user_id', 'zone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_beach_zones');
    }
};
