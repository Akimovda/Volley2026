<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Курорты Таиланда, Индонезии (Бали) и ОАЭ: русские названия, «о. Пхукет, Патонг» / «о. Бали, Убуд», общие алиасы острова и популярные места.
 * Города должны быть уже импортированы (cities:sync-geonames --countries=TH,ID,AE); привязка по cities.geoname_id.
 * Данные — database/data/city_aliases_resorts.php.
 */
return new class extends Migration {
    public function up(): void
    {
        $path = database_path('data/city_aliases_resorts.php');
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
    }

    public function down(): void
    {
        $path = database_path('data/city_aliases_resorts.php');
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
