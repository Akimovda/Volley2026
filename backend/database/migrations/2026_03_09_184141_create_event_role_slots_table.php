<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_role_slots', function (Blueprint $table) {

            $table->id();

            $table->foreignId('event_id')
                ->constrained('events')
                ->cascadeOnDelete();

            $table->string('role', 50);

            $table->unsignedInteger('max_slots');

            $table->unsignedInteger('taken_slots')
                ->default(0);

            $table->timestamps();

            $table->unique(['event_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_role_slots');
    }
};
