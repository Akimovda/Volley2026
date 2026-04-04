<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('event_team_invites')) {
            return;
        }

        Schema::create('event_team_invites', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('event_team_id');
            $table->unsignedBigInteger('invited_user_id');
            $table->unsignedBigInteger('invited_by_user_id')->nullable();

            $table->string('team_role', 32)->default('player');
            $table->string('position_code', 32)->nullable();

            $table->string('token', 120)->unique();
            $table->string('status', 32)->default('pending');

            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('revoked_at')->nullable();

            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['event_team_id', 'invited_user_id']);
            $table->index(['event_id', 'status']);
        });

        DB::statement('ALTER TABLE event_team_invites ADD CONSTRAINT event_team_invites_event_id_foreign FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE event_team_invites ADD CONSTRAINT event_team_invites_event_team_id_foreign FOREIGN KEY (event_team_id) REFERENCES event_teams(id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE event_team_invites ADD CONSTRAINT event_team_invites_invited_user_id_foreign FOREIGN KEY (invited_user_id) REFERENCES users(id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE event_team_invites ADD CONSTRAINT event_team_invites_invited_by_user_id_foreign FOREIGN KEY (invited_by_user_id) REFERENCES users(id) ON DELETE SET NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('event_team_invites');
    }
};
