<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Свои названия/цвета 7 уровней: на бренд приложения или на организатора (школу).
        Schema::create('level_schemes', function (Blueprint $table) {
            $table->id();
            $table->string('owner_type', 16); // brand | organizer
            $table->unsignedBigInteger('owner_id');
            $table->unsignedTinyInteger('level'); // 1..7 — значения в БД не меняются, только подписи
            $table->string('name_ru', 60);
            $table->string('name_en', 60)->nullable();
            $table->string('short_ru', 20)->nullable();
            $table->string('short_en', 20)->nullable();
            $table->string('color', 7)->nullable();      // фон пилюли
            $table->string('text_color', 7)->nullable(); // текст пилюли
            $table->timestamps();
            $table->unique(['owner_type', 'owner_id', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('level_schemes');
    }
};
