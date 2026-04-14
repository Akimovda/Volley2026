<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_private_accesses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('event_id');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'event_id']);
            $table->index('event_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_private_accesses');
    }
};
