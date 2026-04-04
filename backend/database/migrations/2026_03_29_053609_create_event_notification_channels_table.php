<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('event_notification_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('channel_id')->constrained('user_notification_channels')->cascadeOnDelete();

            $table->string('notification_type', 32)->default('registration_open');
            $table->boolean('use_private_link')->default(false);
            $table->boolean('silent')->default(false);
            $table->boolean('update_message')->default(true);
            $table->boolean('include_image')->default(true);
            $table->boolean('include_registered_list')->default(true);

            $table->timestamps();

            $table->unique(['event_id', 'channel_id', 'notification_type'], 'uniq_event_channel_type');
            $table->index(['event_id', 'notification_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_notification_channels');
    }
};
