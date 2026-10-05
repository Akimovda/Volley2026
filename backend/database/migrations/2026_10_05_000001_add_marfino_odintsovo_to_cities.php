<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    // д. Марфино, Московская область, г.о. Одинцовский
    public function up(): void
    {
        $exists = DB::table('cities')
            ->where('name', 'Марфино')
            ->where('region', 'Московская область')
            ->exists();

        if (!$exists) {
            DB::table('cities')->insert([
                'name'         => 'Марфино',
                'region'       => 'Московская область',
                'country_code' => 'RU',
                'timezone'     => 'Europe/Moscow',
                'lat'          => 55.7017000,
                'lon'          => 37.3831000,
                'population'   => 279,
                'geoname_id'   => null,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('cities')
            ->where('name', 'Марфино')
            ->where('region', 'Московская область')
            ->whereNull('geoname_id')
            ->delete();
    }
};
