<?php

use App\Support\CountryRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Страны как данные: все страны мира (ISO 3166-1 alpha-2, названия ru/en из Unicode CLDR) для поиска в «+ Добавить город».
 * is_active — страна «поддерживается» (доступна в профиле и в поиске городов): 45 исходных стран (CountryRules::SUPPORTED)
 * активны сразу, остальные включаются автоматически, когда в такой стране добавляют первый город (CountryRules::activate()).
 * Данные — database/data/countries.php.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('countries')) {
            Schema::create('countries', function (Blueprint $t) {
                $t->string('code', 2)->primary();
                $t->string('name_ru', 120);
                $t->string('name_en', 120);
                $t->string('alt_ru', 120)->nullable();
                $t->string('alt_en', 120)->nullable();
                $t->boolean('is_active')->default(false)->index();
                $t->string('source', 20)->default('cldr');
                $t->timestamps();
            });
        }

        $path = database_path('data/countries.php');
        if (!is_file($path)) {
            return;
        }
        $data = require $path;
        $now = now();
        $rows = [];
        foreach ($data as $code => [$ru, $en, $altRu, $altEn]) {
            $rows[] = [
                'code'       => $code,
                'name_ru'    => $ru,
                'name_en'    => $en,
                'alt_ru'     => $altRu !== '' ? $altRu : null,
                'alt_en'     => $altEn !== '' ? $altEn : null,
                'is_active'  => in_array($code, CountryRules::SUPPORTED, true),
                'source'     => 'cldr',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('countries')->insertOrIgnore($chunk);
        }
        // на случай повторного запуска: исходные страны всегда активны
        DB::table('countries')->whereIn('code', CountryRules::SUPPORTED)->update(['is_active' => true]);
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
