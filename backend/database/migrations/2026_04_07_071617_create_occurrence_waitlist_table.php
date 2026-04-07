<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('occurrence_waitlist', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('occurrence_id');
            $table->unsignedBigInteger('user_id');
            $table->json('positions')->default('[]'); // [] для пляжки
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('notification_expires_at')->nullable();
            $table->timestamps();

            $table->unique(['occurrence_id', 'user_id']);
            $table->foreign('occurrence_id')->references('id')->on('event_occurrences')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['occurrence_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('occurrence_waitlist');
    }
};
