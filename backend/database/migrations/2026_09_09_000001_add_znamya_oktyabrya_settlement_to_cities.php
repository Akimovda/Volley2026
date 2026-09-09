<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $exists = DB::table('cities')
            ->where('name', 'Знамя Октября')
            ->where('region', 'Москва')
            ->exists();

        if (!$exists) {
            DB::table('cities')->insert([
                'name'         => 'Знамя Октября',
                'region'       => 'Москва',
                'country_code' => 'RU',
                'timezone'     => 'Europe/Moscow',
                'lat'          => 55.4756000,
                'lon'          => 37.5380000,
                'population'   => 7394,
                'geoname_id'   => null,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('cities')
            ->where('name', 'Знамя Октября')
            ->where('region', 'Москва')
            ->whereNull('geoname_id')
            ->delete();
    }
};
