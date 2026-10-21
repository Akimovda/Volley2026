<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('broadcast_destinations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('court_id')->constrained('event_courts')->cascadeOnDelete();
            $t->string('platform', 20);
            $t->string('label', 100)->nullable();
            $t->string('server_url', 300);
            $t->text('stream_key'); // Laravel encrypted cast — в БД только шифр
            $t->boolean('is_default')->default(false);
            $t->timestamps();
            $t->index('court_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_destinations');
    }
};
