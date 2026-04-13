<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('staff_user_id');
            $table->unsignedBigInteger('organizer_id');
            $table->timestamps();

            $table->unique('staff_user_id'); // один staff — один организатор
            $table->foreign('staff_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('organizer_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_assignments');
    }
};
