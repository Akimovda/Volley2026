<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        Brand::updateOrCreate(
            ['slug' => 'volleyplay'],
            [
                'ua_suffix' => 'VolleyPlayApp',
                'display_name' => 'Volley Club',
                'is_default' => true,
            ]
        );

        Brand::updateOrCreate(
            ['slug' => 'svsvolley'],
            [
                'ua_suffix' => 'SVSVolleyApp',
                'display_name' => 'SVS Volley',
                'logo_day_path' => 'icons/logo svs day.png',
                'logo_night_path' => 'icons/Logo svs night.png',
                'is_default' => false,
            ]
        );
    }
}
