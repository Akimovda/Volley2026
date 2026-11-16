<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Мальдивы, Шри-Ланка, Греция (Крит, Родос, Корфу, Санторини…), Кипр, Мексика, Куба, Доминикана: русские названия и латинские
 * синонимы, «о. Крит, Ираклион», общие алиасы острова/района, популярные места; новые страны (MV, LK, MX, CU, DO) включаются.
 * Города должны быть уже импортированы (cities:sync-geonames --countries=...); привязка по cities.geoname_id.
 * Данные — database/data/city_aliases_dest2.php.
 */
return new class extends Migration {
    public function up(): void
    {
        $path = database_path('data/city_aliases_dest2.php');
        if (!is_file($path)) {
            return;
        }
        $data = require $path;
        $now = now();

        foreach ([['aliases', 'ru'], ['aliases_en', 'en']] as [$key, $locale]) {
            foreach (array_chunk($data[$key] ?? [], 300, true) as $chunk) {
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
                            'locale'     => $locale,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }
                if ($rows) {
                    DB::table('city_aliases')->insertOrIgnore($rows);
                }
            }
        }

        if (!empty($data['featured'])) {
            DB::table('cities')->whereIn('geoname_id', $data['featured'])->update(['is_featured' => true]);
        }

        // новые страны становятся «поддерживаемыми» (профиль, поиск городов), если в них уже есть города
        $new = DB::table('cities')->whereIn('country_code', ['MV', 'LK', 'MX', 'CU', 'DO'])->distinct()->pluck('country_code')->all();
        if ($new) {
            DB::table('countries')->whereIn('code', $new)->update(['is_active' => true, 'updated_at' => $now]);
            Cache::forget('countries_map_v1');
        }
    }

    public function down(): void
    {
        $path = database_path('data/city_aliases_dest2.php');
        if (!is_file($path)) {
            return;
        }
        $data = require $path;
        $geoIds = array_unique(array_merge(array_keys($data['aliases'] ?? []), array_keys($data['aliases_en'] ?? [])));
        $cityIds = DB::table('cities')->whereIn('geoname_id', $geoIds)->pluck('id');
        DB::table('city_aliases')->whereIn('city_id', $cityIds)->delete();
        DB::table('cities')->whereIn('geoname_id', $data['featured'] ?? [])->update(['is_featured' => false]);
    }
};
