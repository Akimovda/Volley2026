<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Китай: русские названия для найденных GeoNames городов и курортов (Хайнань — «о. Хайнань, Санья»), популярные места выше
 * в выдаче, страна CN включается (появляется в профиле и в общем поиске городов).
 * Города должны быть импортированы заранее (cities:sync-geonames --countries=CN); привязка по cities.geoname_id.
 * Данные — database/data/city_aliases_cn.php.
 */
return new class extends Migration {
    public function up(): void
    {
        $path = database_path('data/city_aliases_cn.php');
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

        // Китай становится «поддерживаемой» страной (профиль, поиск городов)
        if (DB::table('cities')->where('country_code', 'CN')->exists()) {
            DB::table('countries')->where('code', 'CN')->update(['is_active' => true, 'updated_at' => $now]);
            Cache::forget('countries_map_v1');
        }
    }

    public function down(): void
    {
        $path = database_path('data/city_aliases_cn.php');
        if (!is_file($path)) {
            return;
        }
        $data = require $path;
        $cityIds = DB::table('cities')->whereIn('geoname_id', array_keys($data['aliases'] ?? []))->pluck('id');
        DB::table('city_aliases')->whereIn('city_id', $cityIds)->delete();
        DB::table('cities')->whereIn('geoname_id', $data['featured'] ?? [])->update(['is_featured' => false]);
    }
};
