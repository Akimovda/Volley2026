<?php

namespace Database\Seeders;

use App\Models\Event;
use Illuminate\Database\Seeder;

class EventSeeder extends Seeder
{
    public function run(): void
    {
        Event::query()->delete();

        Event::create([
            'title' => 'Тест: Открытая игра (без требований)',
            'requires_personal_data' => false,
            'classic_level_min' => null,
            'beach_level_min' => null,
        ]);

        Event::create([
            'title' => 'Тест: Классика — уровень от 3',
            'requires_personal_data' => true,
            'classic_level_min' => 3,
            'beach_level_min' => null,
        ]);

        Event::create([
            'title' => 'Тест: Пляж — уровень от 2',
            'requires_personal_data' => true,
            'classic_level_min' => null,
            'beach_level_min' => 2,
        ]);
    }
}
