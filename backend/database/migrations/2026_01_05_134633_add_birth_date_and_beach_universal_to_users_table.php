<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // дата рождения для правил <18 / >=18
            $table->date('birth_date')->nullable()->after('phone');

            // пляж: признак "универсал" (если true — зоны не выделяются)
            $table->boolean('beach_universal')->default(false)->after('beach_level');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['birth_date', 'beach_universal']);
        });
    }
};

