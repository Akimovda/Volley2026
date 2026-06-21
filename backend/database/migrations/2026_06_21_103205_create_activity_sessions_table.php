<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('activity_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('occurrence_id')->nullable()->nullOnDelete()->constrained('event_occurrences');
            $table->foreignId('device_id')->nullable()->nullOnDelete()->constrained('athlete_devices');
            $table->string('direction')->nullable();
            $table->string('status')->default('live');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_sec')->nullable();
            $table->unsignedSmallInteger('avg_hr')->nullable();
            $table->unsignedSmallInteger('max_hr')->nullable();
            $table->unsignedSmallInteger('min_hr')->nullable();
            $table->jsonb('time_in_zone')->nullable();
            $table->decimal('load_score', 8, 2)->nullable();
            $table->unsignedInteger('samples_count')->default(0);
            $table->timestamps();
            $table->index(['user_id', 'started_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_sessions');
    }
};
