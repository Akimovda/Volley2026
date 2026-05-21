<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('king_beach_standings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stage_id')->constrained('tournament_stages')->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('tournament_groups')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('total_points')->default(0);
            $table->unsignedSmallInteger('rank')->default(0);
            $table->timestamps();

            $table->unique(['stage_id', 'group_id', 'user_id']);
            $table->index(['group_id', 'rank']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('king_beach_standings');
    }
};
