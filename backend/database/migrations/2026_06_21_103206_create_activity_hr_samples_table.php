<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('activity_hr_samples', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('session_id')->constrained('activity_sessions')->cascadeOnDelete();
            $table->unsignedInteger('t_offset_sec');
            $table->unsignedSmallInteger('bpm');
            $table->unique(['session_id', 't_offset_sec']);
            $table->index('session_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_hr_samples');
    }
};
