<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('court_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('court_id')
                ->constrained('location_courts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()
                ->constrained()->nullOnDelete();
            $table->foreignId('occurrence_id')->nullable()
                ->constrained('event_occurrences')->nullOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status', 20)->default('pending');
            // pending | confirmed | paid | cancelled | expired
            $table->decimal('price_total', 8, 2)->nullable();
            $table->string('payment_mode', 20)->default('on_site');
            // prepaid | on_site | trusted
            $table->dateTime('expires_at')->nullable();
            $table->string('cancelled_by', 10)->nullable(); // user|club|system
            $table->text('cancel_reason')->nullable();
            $table->timestamps();
            $table->index(['court_id', 'starts_at', 'ends_at']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('court_bookings');
    }
};
