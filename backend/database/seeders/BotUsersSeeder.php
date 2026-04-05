<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class BotUsersSeeder extends Seeder
{
    public function run(): void
    {
        // Получаем ID города Саратов (или первый доступный)
        $cityId = DB::table('cities')->where('name', 'like', '%Саратов%')->value('id')
            ?? DB::table('cities')->value('id');

        $males = $this->maleData();
        $females = $this->femaleData();

        foreach ($males as $data) {
            $this->createBot($data, 'm', $cityId);
        }

        foreach ($females as $data) {
            $this->createBot($data, 'f', $cityId);
        }

        $this->command->info('✅ Создано ' . count($males) . ' ботов-мужчин и ' . count($females) . ' ботов-женщин.');
    }

    private function createBot(array $data, string $gender, ?int $cityId): void
    {
        $email = Str::slug($data['first_name'] . '.' . $data['last_name'], '.') . '.' . rand(100, 999) . '@bot.local';

        // classic_level 4–5 (smallint в БД)
        $classicLevel = mt_rand(4, 5);
        $beachLevel   = mt_rand(3, 5);

        // Возраст 25–35 (без високосных дат — используем addYears безопасно)
        $age       = mt_rand(25, 35);
        $birthDate = now()->subYears($age)->subDays(mt_rand(0, 355))->format('Y-m-d');

        // Рост: мужчины 178–196, женщины 165–180
        $height = $gender === 'm'
            ? mt_rand(178, 196)
            : mt_rand(165, 180);

        DB::table('users')->insertOrIgnore([
            'name'                => $data['first_name'] . ' ' . $data['last_name'],
            'first_name'          => $data['first_name'],
            'last_name'           => $data['last_name'],
            'patronymic'          => $data['patronymic'] ?? null,
            'email'               => $email,
            'email_verified_at'   => now(),
            'password'            => Hash::make(Str::random(32)), // войти невозможно
            'role'                => 'user',
            'is_bot'              => true,
            'gender'              => $gender,
            'birth_date'          => $birthDate,
            'classic_level'       => $classicLevel,
            'beach_level'         => $beachLevel,
            'height_cm'           => $height,
            'city_id'             => $cityId,
            'allow_user_contact'  => false,
            'created_at'          => now()->subDays(mt_rand(30, 180)), // выглядит как давний игрок
            'updated_at'          => now(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Данные ботов — реалистичные русские имена
    // -------------------------------------------------------------------------

    private function maleData(): array
    {
        return [
            ['first_name' => 'Алексей',    'last_name' => 'Морозов',    'patronymic' => 'Сергеевич'],
            ['first_name' => 'Дмитрий',    'last_name' => 'Волков',     'patronymic' => 'Андреевич'],
            ['first_name' => 'Иван',       'last_name' => 'Козлов',     'patronymic' => 'Николаевич'],
            ['first_name' => 'Максим',     'last_name' => 'Новиков',    'patronymic' => 'Алексеевич'],
            ['first_name' => 'Артём',      'last_name' => 'Лебедев',    'patronymic' => 'Дмитриевич'],
            ['first_name' => 'Никита',     'last_name' => 'Соколов',    'patronymic' => 'Игоревич'],
            ['first_name' => 'Андрей',     'last_name' => 'Попов',      'patronymic' => 'Михайлович'],
            ['first_name' => 'Кирилл',     'last_name' => 'Захаров',    'patronymic' => 'Владимирович'],
            ['first_name' => 'Роман',      'last_name' => 'Павлов',     'patronymic' => 'Евгеньевич'],
            ['first_name' => 'Илья',       'last_name' => 'Семёнов',    'patronymic' => 'Романович'],
            ['first_name' => 'Евгений',    'last_name' => 'Голубев',    'patronymic' => 'Васильевич'],
            ['first_name' => 'Сергей',     'last_name' => 'Виноградов', 'patronymic' => 'Петрович'],
            ['first_name' => 'Павел',      'last_name' => 'Богданов',   'patronymic' => 'Олегович'],
            ['first_name' => 'Владимир',   'last_name' => 'Кузьмин',    'patronymic' => 'Сергеевич'],
            ['first_name' => 'Михаил',     'last_name' => 'Тихонов',    'patronymic' => 'Юрьевич'],
            ['first_name' => 'Олег',       'last_name' => 'Медведев',   'patronymic' => 'Дмитриевич'],
            ['first_name' => 'Станислав',  'last_name' => 'Фёдоров',    'patronymic' => 'Антонович'],
            ['first_name' => 'Тимур',      'last_name' => 'Орлов',      'patronymic' => 'Ренатович'],
            ['first_name' => 'Антон',      'last_name' => 'Белов',      'patronymic' => 'Игоревич'],
            ['first_name' => 'Денис',      'last_name' => 'Гусев',      'patronymic' => 'Александрович'],
        ];
    }

    private function femaleData(): array
    {
        return [
            ['first_name' => 'Анастасия',  'last_name' => 'Морозова',    'patronymic' => 'Сергеевна'],
            ['first_name' => 'Екатерина',  'last_name' => 'Волкова',     'patronymic' => 'Андреевна'],
            ['first_name' => 'Мария',      'last_name' => 'Козлова',     'patronymic' => 'Николаевна'],
            ['first_name' => 'Дарья',      'last_name' => 'Новикова',    'patronymic' => 'Алексеевна'],
            ['first_name' => 'Ольга',      'last_name' => 'Лебедева',    'patronymic' => 'Дмитриевна'],
            ['first_name' => 'Юлия',       'last_name' => 'Соколова',    'patronymic' => 'Игоревна'],
            ['first_name' => 'Алина',      'last_name' => 'Попова',      'patronymic' => 'Михайловна'],
            ['first_name' => 'Вероника',   'last_name' => 'Захарова',    'patronymic' => 'Владимировна'],
            ['first_name' => 'Наталья',    'last_name' => 'Павлова',     'patronymic' => 'Евгеньевна'],
            ['first_name' => 'Виктория',   'last_name' => 'Семёнова',    'patronymic' => 'Романовна'],
            ['first_name' => 'Елена',      'last_name' => 'Голубева',    'patronymic' => 'Васильевна'],
            ['first_name' => 'Ксения',     'last_name' => 'Виноградова', 'patronymic' => 'Петровна'],
            ['first_name' => 'Полина',     'last_name' => 'Богданова',   'patronymic' => 'Олеговна'],
            ['first_name' => 'Валерия',    'last_name' => 'Кузьмина',    'patronymic' => 'Сергеевна'],
            ['first_name' => 'Ирина',      'last_name' => 'Тихонова',    'patronymic' => 'Юрьевна'],
            ['first_name' => 'Татьяна',    'last_name' => 'Медведева',   'patronymic' => 'Дмитриевна'],
            ['first_name' => 'Анна',       'last_name' => 'Фёдорова',    'patronymic' => 'Антоновна'],
            ['first_name' => 'Светлана',   'last_name' => 'Орлова',      'patronymic' => 'Ренатовна'],
            ['first_name' => 'Александра', 'last_name' => 'Белова',      'patronymic' => 'Игоревна'],
            ['first_name' => 'Надежда',    'last_name' => 'Гусева',      'patronymic' => 'Александровна'],
        ];
    }
}
