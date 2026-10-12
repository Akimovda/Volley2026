<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Страна пользователя — ось правил ФИО/телефона/справочника городов.
            // Дефолт RU: поведение существующих пользователей не меняется.
            $table->string('country_code', 2)->default('RU')->after('city_id');
        });

        // Те, у кого город из другой страны справочника (KZ/UZ), получают её код.
        DB::statement("
            UPDATE users u
            SET country_code = c.country_code
            FROM cities c
            WHERE c.id = u.city_id
              AND c.country_code IS NOT NULL
              AND c.country_code <> 'RU'
        ");
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('country_code');
        });
    }
};
