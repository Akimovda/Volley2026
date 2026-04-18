<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_standings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stage_id')->constrained('tournament_stages')->cascadeOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('tournament_groups')->nullOnDelete();
            $table->foreignId('team_id')->constrained('event_teams')->cascadeOnDelete();

            $table->unsignedSmallInteger('played')->default(0);
            $table->unsignedSmallInteger('wins')->default(0);
            $table->unsignedSmallInteger('losses')->default(0);
            $table->unsignedSmallInteger('draws')->default(0);

            $table->unsignedSmallInteger('sets_won')->default(0);
            $table->unsignedSmallInteger('sets_lost')->default(0);
            $table->unsignedInteger('points_scored')->default(0);
            $table->unsignedInteger('points_conceded')->default(0);

            $table->unsignedSmallInteger('rating_points')->default(0);
            $table->unsignedSmallInteger('rank')->default(0);

            $table->timestamps();

            $table->unique(['stage_id', 'group_id', 'team_id']);
            $table->index(['stage_id', 'group_id', 'rank']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_standings');
    }
};
