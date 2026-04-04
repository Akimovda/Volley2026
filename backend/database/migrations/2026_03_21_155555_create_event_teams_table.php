<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_teams', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_id')
                ->constrained('events')
                ->cascadeOnDelete();

            $table->foreignId('occurrence_id')
                ->nullable()
                ->constrained('event_occurrences')
                ->nullOnDelete();

            $table->foreignId('captain_user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('name', 255);
            $table->string('team_kind', 32);
            // classic_team | beach_pair

            $table->string('status', 32)->default('draft');
            // draft | pending_members | ready | pending | confirmed | rejected | withdrawn | waitlist

            $table->string('invite_code', 64)->unique();

            $table->boolean('is_complete')->default(false);
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['event_id', 'status']);
            $table->index(['event_id', 'team_kind']);
            $table->index(['occurrence_id', 'status']);
            $table->index(['captain_user_id']);
            $table->index(['is_complete']);
            $table->unique(['event_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_teams');
    }
};
