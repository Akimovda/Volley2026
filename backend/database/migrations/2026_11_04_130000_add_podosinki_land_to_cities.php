<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    // КП Подосинки-лэнд, сельское поселение Иваньковское, Ясногорский район, Тульская область
    public function up(): void
    {
        $exists = DB::table('cities')
            ->where('name', 'КП Подосинки-лэнд')
            ->where('region', 'Тульская область')
            ->exists();

        if (!$exists) {
            DB::table('cities')->insert([
                'name'         => 'КП Подосинки-лэнд',
                'region'       => 'Тульская область',
                'country_code' => 'RU',
                'timezone'     => 'Europe/Moscow',
                'lat'          => 54.8108280,
                'lon'          => 37.9153460,
                'population'   => null,
                'geoname_id'   => null,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('cities')
            ->where('name', 'КП Подосинки-лэнд')
            ->where('region', 'Тульская область')
            ->whereNull('geoname_id')
            ->delete();
    }
};
