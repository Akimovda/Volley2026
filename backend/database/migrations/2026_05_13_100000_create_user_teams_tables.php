<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_teams', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->string('name', 255);
            $table->string('direction', 32)->default('classic');
            $table->string('subtype', 16)->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('user_team_members', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_team_id');
            $table->unsignedBigInteger('user_id');
            $table->string('role_code', 32)->default('player');
            $table->string('position_code', 32)->nullable();
            $table->timestamps();

            $table->foreign('user_team_id')->references('id')->on('user_teams')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['user_team_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_team_members');
        Schema::dropIfExists('user_teams');
    }
};
