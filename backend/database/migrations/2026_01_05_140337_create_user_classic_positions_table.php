<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_classic_positions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // setter, outside, opposite, middle, libero
            $table->string('position');

            $table->boolean('is_primary')->default(false);

            $table->timestamps();

            // один пользователь — одно амплуа только один раз
            $table->unique(['user_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_classic_positions');
    }
};
