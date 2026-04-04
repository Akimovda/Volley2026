<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_registration_group_invites', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();

            $table->string('group_key', 64);

            $table->foreignId('from_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('to_user_id')->constrained('users')->cascadeOnDelete();

            $table->string('status', 24)->default('pending'); // pending|accepted|declined|cancelled

            $table->timestamps();

            $table->index(['event_id', 'group_key']);
            $table->index(['to_user_id', 'status']);
            $table->unique(['event_id', 'group_key', 'to_user_id'], 'ergi_event_group_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_registration_group_invites');
    }
};
