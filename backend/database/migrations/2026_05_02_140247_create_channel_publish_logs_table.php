<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_publish_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('occurrence_id');
            $table->unsignedBigInteger('channel_id');
            $table->string('platform', 20);
            $table->string('action', 20); // send | update | skip | fail
            $table->string('notification_type', 50)->default('registration_open');
            $table->text('error')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['event_id', 'occurrence_id']);
            $table->index(['channel_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_publish_logs');
    }
};
