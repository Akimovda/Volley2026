<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('locations')) {
            return;
        }

        Schema::create('locations', function (Blueprint $table) {
            $table->id();

            // Кто создал/владеет локацией (организатор), nullable чтобы admin мог заводить общие
            $table->unsignedBigInteger('organizer_id')->nullable()->index();

            $table->string('name');                 // название (Зал/Пляж/Центр)
            $table->string('address')->nullable();  // адрес одной строкой
            $table->string('city')->nullable();
            $table->string('timezone')->default('Europe/Berlin'); // IANA timezone
            $table->text('note')->nullable();       // примечание

            $table->timestamps();

            $table->foreign('organizer_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
