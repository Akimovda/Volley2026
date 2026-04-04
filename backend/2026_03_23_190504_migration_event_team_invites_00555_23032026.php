<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_team_invites', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_team_id')->constrained('event_teams')->cascadeOnDelete();

            $table->foreignId('invited_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('invited_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('team_role', 32)->default('player');   // player|reserve
            $table->string('position_code', 32)->nullable();      // setter|outside|...

            $table->string('token', 120)->unique();
            $table->string('status', 32)->default('pending');     // pending|accepted|declined|revoked|expired

            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('revoked_at')->nullable();

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['event_team_id', 'invited_user_id']);
            $table->index(['event_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_team_invites');
    }
};
