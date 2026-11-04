<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Поиск городов по-русски и без диакритики (Вьетнам, Турция).
 *  - cities.is_featured — популярные города/курорты (поднимаются выше при запросе по стране);
 *  - city_aliases — альтернативные названия (русские написания); alias_norm — нижний регистр, ё→е,
 *    считается в PHP (LOWER() в этой БД не понижает кириллицу);
 *  - расширение unaccent — «Kusadasi» находит «Kuşadası», «Istanbul» — «İstanbul».
 * Данные — database/data/city_aliases.php (geoname_id => названия), привязка по cities.geoname_id.
 */
return new class extends Migration {
    public function up(): void
    {
        try {
            DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent');
        } catch (\Throwable $e) {
            // нет прав — расширение можно создать вручную от postgres; поиск тогда работает без unaccent
        }

        if (!Schema::hasColumn('cities', 'is_featured')) {
            Schema::table('cities', function (Blueprint $t) {
                $t->boolean('is_featured')->default(false)->index();
            });
        }

        if (!Schema::hasTable('city_aliases')) {
            Schema::create('city_aliases', function (Blueprint $t) {
                $t->id();
                $t->foreignId('city_id')->constrained('cities')->cascadeOnDelete();
                $t->string('alias', 120);
                $t->string('alias_norm', 120)->index();
                $t->string('locale', 5)->default('ru');
                $t->timestamps();
                $t->unique(['city_id', 'alias']);
            });
        }

        $path = database_path('data/city_aliases.php');
        if (!is_file($path)) {
            return;
        }
        $data = require $path;
        $now = now();

        foreach (array_chunk($data['aliases'] ?? [], 300, true) as $chunk) {
            $ids = DB::table('cities')->whereIn('geoname_id', array_keys($chunk))->pluck('id', 'geoname_id');
            $rows = [];
            foreach ($chunk as $geoId => $names) {
                $cityId = $ids[$geoId] ?? null;
                if (!$cityId) {
                    continue;
                }
                foreach ($names as $name) {
                    $rows[] = [
                        'city_id'    => $cityId,
                        'alias'      => $name,
                        'alias_norm' => mb_strtolower(str_replace(['ё', 'Ё'], 'е', $name)),
                        'locale'     => 'ru',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
            if ($rows) {
                DB::table('city_aliases')->insertOrIgnore($rows);
            }
        }

        if (!empty($data['featured'])) {
            DB::table('cities')->whereIn('geoname_id', $data['featured'])->update(['is_featured' => true]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('city_aliases');
        if (Schema::hasColumn('cities', 'is_featured')) {
            Schema::table('cities', function (Blueprint $t) {
                $t->dropColumn('is_featured');
            });
        }
    }
};
