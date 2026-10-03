<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Тест на уровень игры: 16 вопросов (8 блоков × 2: «как умеешь» + «сколько раз из 10»), сумма 0..48.
 * Варианты в тексте идут вперемешку, поэтому клиенты шлют ИНДЕКС выбранного варианта (0..3),
 * а балл берётся из SCORES — на экране баллы не видны. Тексты — lang/{ru,en}/leveltest.php.
 * Логика итога и ограничений — только здесь (сайт и боты Telegram/MAX берут и вопросы, и результат у сервиса).
 */
class LevelTestService
{
    public const DISCIPLINES = ['classic', 'beach'];
    public const QUESTIONS_COUNT = 16;
    public const MAX_SCORE = 48;

    /** По возрастанию. */
    public const LEVELS = ['beginner', 'beginner_plus', 'middle_minus', 'middle'];

    /**
     * Фото к блокам вопросов (по одному на 2 вопроса) — Wikimedia Commons, свободные лицензии.
     * credit=true — лицензия требует указания автора (CC BY / CC BY-SA): строка добавляется в подпись в ботах, список — на странице теста.
     */
    public const PHOTOS = [
        1 => ['author' => 'Zorro2212', 'license' => 'CC BY-SA 3.0', 'license_url' => 'https://creativecommons.org/licenses/by-sa/3.0', 'source' => 'https://commons.wikimedia.org/wiki/File:Serve_during_volleyball_match_4.jpg', 'credit' => true],
        2 => ['author' => 'Todd Ryburn from Bloomington, IL, United States', 'license' => 'CC BY 2.0', 'license_url' => 'https://creativecommons.org/licenses/by/2.0', 'source' => 'https://commons.wikimedia.org/wiki/File:Volleyball_bump_(15630611667).jpg', 'credit' => true],
        3 => ['author' => 'Zorro2212', 'license' => 'CC BY-SA 4.0', 'license_url' => 'https://creativecommons.org/licenses/by-sa/4.0', 'source' => 'https://commons.wikimedia.org/wiki/File:Joanna_Wo%C5%82osz_is_setting.jpg', 'credit' => true],
        4 => ['author' => 'Chris Hunkeler from Carlsbad, California, USA', 'license' => 'CC BY-SA 2.0', 'license_url' => 'https://creativecommons.org/licenses/by-sa/2.0', 'source' => 'https://commons.wikimedia.org/wiki/File:Spike_(7168982691).jpg', 'credit' => true],
        5 => ['author' => 'Wikimedia Commons', 'license' => 'Public domain', 'license_url' => '', 'source' => 'https://commons.wikimedia.org/wiki/File:Volleyball_block.jpg', 'credit' => false],
        6 => ['author' => 'Wikimedia Commons', 'license' => 'Public domain', 'license_url' => '', 'source' => 'https://commons.wikimedia.org/wiki/File:Overhand_dig.jpg', 'credit' => false],
        7 => ['author' => 'Dispe', 'license' => 'CC0', 'license_url' => 'http://creativecommons.org/publicdomain/zero/1.0/deed.en', 'source' => 'https://commons.wikimedia.org/wiki/File:2026_Men%27s_European_Volleyball_Championship_-_Italy_vs_Finland_-_Torino,_Palavela,_23.09.2026_-_440.jpg', 'credit' => false],
        8 => ['author' => 'SSgt Timothy Chacon, U.S. Air Force', 'license' => 'Public domain', 'license_url' => '', 'source' => 'https://commons.wikimedia.org/wiki/File:U.S._Air_Force_athletes_huddle_during_a_timeout_in_a_sitting_volleyball_match_at_the_Olympic_Training_Center_in_Colorado_Springs,_Colo.,_Sept_140928-F-PD696-247.jpg', 'credit' => false],
    ];

    /** Баллы по позиции варианта в lang-массиве: SCORES[дисциплина][вопрос][индекс варианта]. Менять синхронно с lang. */
    private const SCORES = [
        'classic' => [
            [3, 0, 1, 2],
            [3, 0, 2, 1],
            [1, 2, 0, 3],
            [1, 3, 0, 2],
            [0, 2, 1, 3],
            [2, 0, 3, 1],
            [1, 3, 0, 2],
            [3, 1, 2, 0],
            [1, 3, 0, 2],
            [2, 0, 3, 1],
            [1, 3, 0, 2],
            [1, 3, 2, 0],
            [3, 0, 2, 1],
            [2, 3, 0, 1],
            [1, 3, 0, 2],
            [2, 0, 3, 1],
        ],
        'beach' => [
            [2, 0, 3, 1],
            [1, 3, 0, 2],
            [1, 3, 0, 2],
            [3, 0, 2, 1],
            [1, 3, 0, 2],
            [2, 0, 3, 1],
            [1, 3, 0, 2],
            [3, 1, 2, 0],
            [1, 3, 0, 2],
            [2, 0, 3, 1],
            [1, 3, 0, 2],
            [1, 3, 2, 0],
            [3, 0, 2, 1],
            [2, 3, 0, 1],
            [2, 3, 0, 1],
            [2, 0, 3, 1],
        ],
    ];

    /** @return list<array{title:string,options:list<string>,image:string,credit:string}> */
    public function questions(string $discipline, ?string $locale = null): array
    {
        $this->assertDiscipline($discipline);

        $list = trans("leveltest.{$discipline}_questions", [], $locale);

        // 16 вопросов = 8 блоков по 2 вопроса; фото одно на блок (public/img/level-test/block-N.jpg, 1200×900)
        $out = [];
        foreach (array_values((array) $list) as $i => $q) {
            $block = intdiv($i, 2) + 1;
            $ph = self::PHOTOS[$block];
            $out[] = [
                'title' => (string) $q['title'],
                'options' => array_values(array_map('strval', $q['options'])),
                'image' => asset('img/level-test/block-' . $block . '.jpg'),
                'credit' => !empty($ph['credit']) ? trim(explode(' from ', $ph['author'])[0]) . ', ' . $ph['license'] : '',
            ];
        }

        return $out;
    }

    /**
     * @param  array<int,int|string>  $answers  16 индексов выбранных вариантов (0..3) в порядке вопросов
     * @return array{level:string,score:int,max:int,capped:bool}
     */
    public function evaluate(string $discipline, array $answers): array
    {
        $this->assertDiscipline($discipline);

        $answers = array_values($answers);
        if (count($answers) !== self::QUESTIONS_COUNT) {
            throw new InvalidArgumentException('Expected 16 answers');
        }

        $a = [];
        foreach ($answers as $q => $idx) {
            if (!is_numeric($idx) || (string) (int) $idx !== (string) $idx || (int) $idx < 0 || (int) $idx > 3) {
                throw new InvalidArgumentException('Answer must be option index 0..3');
            }
            $a[$q] = self::SCORES[$discipline][$q][(int) $idx];
        }
        $score = array_sum($a);

        $idx = match (true) {
            $score <= 11 => 0,
            $score <= 23 => 1,
            $score <= 35 => 2,
            default => 3,
        };

        // «Средний» — только если в ключевых процентных вопросах (приём, пас, атака, тактика) балл ≥ 2.
        // Номера вопросов 4, 6, 8, 16 → индексы 3, 5, 7, 15.
        $maxIdx = 3;
        foreach ([3, 5, 7, 15] as $i) {
            if ($a[$i] < 2) {
                $maxIdx = 2;
                break;
            }
        }

        $final = min($idx, $maxIdx);

        return [
            'level' => self::LEVELS[$final],
            'score' => $score,
            'max' => self::MAX_SCORE,
            'capped' => $final < $idx,
        ];
    }

    /** Заголовок и описание итога для вывода (в ботах и на сайте). */
    public function describe(string $level, ?string $locale = null): array
    {
        return [
            'name' => (string) trans("leveltest.levels.{$level}", [], $locale),
            'text' => (string) trans("leveltest.results.{$level}", [], $locale),
        ];
    }

    private function assertDiscipline(string $discipline): void
    {
        if (!in_array($discipline, self::DISCIPLINES, true)) {
            throw new InvalidArgumentException('Unknown discipline');
        }
    }
}
