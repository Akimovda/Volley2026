<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_team_members', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_team_id')
                ->constrained('event_teams')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('role_code', 32)->default('player');
            // captain | player | reserve | setter | outside | opposite | middle | libero ...

            $table->string('confirmation_status', 32)->default('invited');
            // invited | joined | confirmed | declined | removed

            $table->unsignedSmallInteger('position_order')->nullable();

            $table->foreignId('invited_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('joined_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->unique(['event_team_id', 'user_id']);
            $table->index(['event_team_id', 'confirmation_status']);
            $table->index(['event_team_id', 'role_code']);
            $table->index(['user_id']);
            $table->index(['invited_by_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_team_members');
    }
};
