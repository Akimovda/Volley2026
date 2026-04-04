<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('event_occurrence_stats', function (Blueprint $table) {

            // occurrence_id = primary key
            $table->unsignedBigInteger('occurrence_id')->primary();

            // количество зарегистрированных игроков
            $table->integer('registered_count')->default(0);

            // время последнего обновления
            $table->timestamp('updated_at')->nullable();

            // FK для безопасности данных
            $table->foreign('occurrence_id')
                ->references('id')
                ->on('event_occurrences')
                ->cascadeOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_occurrence_stats');
    }
};
