<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_jump_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('session_id');
            $table->integer('t_offset_sec');
            $table->decimal('height_cm', 5, 1)->nullable();
            $table->string('type', 32)->nullable();

            $table->foreign('session_id')
                  ->references('id')->on('activity_sessions')
                  ->onDelete('cascade');

            $table->unique(['session_id', 't_offset_sec']);
            $table->index('session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_jump_events');
    }
};
