<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('broadcast_channels', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organizer_id')->constrained('users')->cascadeOnDelete();
            $t->string('platform', 20);
            $t->string('label', 100)->nullable();
            $t->string('server_url', 300);
            $t->text('stream_key'); // Laravel encrypted cast — в БД только шифр
            $t->boolean('is_default')->default(false);
            $t->timestamps();
            $t->index('organizer_id');
        });

        Schema::table('broadcast_destinations', function (Blueprint $t) {
            $t->foreignId('channel_id')->nullable()->after('court_id')->constrained('broadcast_channels')->nullOnDelete();
            // у назначения с каналом свои адрес и ключ пусты
            $t->string('server_url', 300)->nullable()->change();
            $t->text('stream_key')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('broadcast_destinations', function (Blueprint $t) {
            $t->dropConstrainedForeignId('channel_id');
        });
        Schema::dropIfExists('broadcast_channels');
    }
};
