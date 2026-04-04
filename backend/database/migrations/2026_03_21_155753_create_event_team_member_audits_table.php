<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_team_member_audits', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_team_id')
                ->constrained('event_teams')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('action', 32);
            // invited | joined | confirmed | declined | removed | role_changed | captain_changed

            $table->foreignId('performed_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->json('meta')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index(['event_team_id', 'action']);
            $table->index(['performed_by_user_id']);
            $table->index(['user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_team_member_audits');
    }
};
