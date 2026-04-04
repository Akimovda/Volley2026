<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_team_applications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_id')
                ->constrained('events')
                ->cascadeOnDelete();

            $table->foreignId('event_team_id')
                ->constrained('event_teams')
                ->cascadeOnDelete();

            $table->string('status', 32)->default('pending');
            // pending | approved | rejected | withdrawn | waitlist

            $table->foreignId('submitted_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('applied_at')->nullable();

            $table->foreignId('reviewed_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('reviewed_at')->nullable();

            $table->text('rejection_reason')->nullable();
            $table->text('decision_comment')->nullable();

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->unique(['event_team_id']);
            $table->index(['event_id', 'status']);
            $table->index(['reviewed_by_user_id']);
            $table->index(['submitted_by_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_team_applications');
    }
};
