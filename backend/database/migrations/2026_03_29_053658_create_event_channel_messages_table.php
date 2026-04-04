<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('event_channel_messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('occurrence_id')->nullable()->constrained('event_occurrences')->cascadeOnDelete();
            $table->foreignId('channel_id')->constrained('user_notification_channels')->cascadeOnDelete();

            $table->string('platform', 32);
            $table->string('external_chat_id', 191)->nullable();
            $table->string('external_message_id', 191)->nullable();

            $table->string('notification_type', 32)->default('registration_open');
            $table->string('last_payload_hash', 64)->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->unique(
                ['occurrence_id', 'channel_id', 'notification_type'],
                'uniq_occurrence_channel_type_msg'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_channel_messages');
    }
};
