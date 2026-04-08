<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('volleyball_schools', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organizer_id'); // владелец
            $table->string('slug')->unique();           // SunVolley
            $table->string('name');                     // Название школы
            $table->string('direction', 20)->default('classic'); // classic|beach|both
            $table->text('description')->nullable();    // Описание
            $table->string('city')->nullable();         // Город
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            $table->foreign('organizer_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
    public function down(): void {
        Schema::dropIfExists('volleyball_schools');
    }
};
