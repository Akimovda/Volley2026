<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_occurrence_trainers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('occurrence_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->foreign('occurrence_id')
                ->references('id')->on('event_occurrences')
                ->cascadeOnDelete();

            $table->foreign('user_id')
                ->references('id')->on('users')
                ->cascadeOnDelete();

            $table->unique(['occurrence_id', 'user_id'], 'eo_trainers_unique');
            $table->index('occurrence_id', 'eo_trainers_occ_idx');
            $table->index('user_id', 'eo_trainers_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_occurrence_trainers');
    }
};
