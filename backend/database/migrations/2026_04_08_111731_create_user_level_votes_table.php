<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('user_level_votes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('voter_id');   // кто голосует
            $table->unsignedBigInteger('target_id');  // за кого голосует
            $table->string('direction', 10);          // 'classic' | 'beach'
            $table->unsignedTinyInteger('level');     // 1–7
            $table->timestamps();

            $table->unique(['voter_id', 'target_id', 'direction']); // 1 голос на направление
            $table->foreign('voter_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('target_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
    public function down(): void {
        Schema::dropIfExists('user_level_votes');
    }
};
