<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // m = мужчина, f = женщина
            $table->string('gender', 1)->nullable()->after('patronymic');
            $table->unsignedSmallInteger('height_cm')->nullable()->after('gender');

            $table->index('gender');
            $table->index('height_cm');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['gender']);
            $table->dropIndex(['height_cm']);

            $table->dropColumn(['gender', 'height_cm']);
        });
    }
};
