<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('user_play_likes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('liker_id');   // кто лайкнул
            $table->unsignedBigInteger('target_id');  // кому лайк
            $table->timestamps();

            $table->unique(['liker_id', 'target_id']); // 1 лайк на человека
            $table->foreign('liker_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('target_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
    public function down(): void {
        Schema::dropIfExists('user_play_likes');
    }
};
