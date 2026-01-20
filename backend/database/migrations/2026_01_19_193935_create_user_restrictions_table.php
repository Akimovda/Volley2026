<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_restrictions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // site | events
            $table->string('scope', 20);

            // null = пожизненно, иначе дата окончания
            $table->timestamp('ends_at')->nullable();

            // для scope=events: список event_id
            $table->json('event_ids')->nullable();

            $table->text('reason')->nullable();

            // admin, который поставил ограничение
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['user_id', 'scope']);
            $table->index(['ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_restrictions');
    }
};
