<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id')->nullable();
            $table->unsignedBigInteger('occurrence_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();

            $table->string('type');    // event_registered, event_cancelled, event_reminder, ...
            $table->string('channel'); // telegram, vk
            $table->string('status')->default('queued'); // queued|sent|failed

            $table->timestampTz('scheduled_at')->nullable();
            $table->timestampTz('sent_at')->nullable();

            $table->string('dedupe_key')->unique(); // защита от дублей
            $table->json('payload')->nullable();
            $table->text('error')->nullable();

            $table->timestamps();

            $table->index(['event_id']);
            $table->index(['occurrence_id']);
            $table->index(['user_id']);
            $table->index(['type']);
            $table->index(['channel']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
    }
};
