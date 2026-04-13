<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('staff_user_id');
            $table->unsignedBigInteger('organizer_id');
            $table->string('action', 100);        // create_event, edit_event, cancel_event, etc.
            $table->string('entity_type', 50)->nullable(); // event, occurrence, registration, subscription
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->text('description')->nullable();
            $table->json('meta')->nullable();      // доп. данные
            $table->timestamps();

            $table->foreign('staff_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('organizer_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['organizer_id', 'created_at']);
            $table->index(['staff_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_logs');
    }
};
