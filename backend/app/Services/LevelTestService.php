<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Тест на уровень игры: 12 вопросов × 0..3 балла, сумма 0..36.
 * Тексты — lang/{ru,en}/leveltest.php; логика итога и ограничений — только здесь
 * (сайт и боты Telegram/MAX берут и вопросы, и результат у этого сервиса).
 */
class LevelTestService
{
    public const DISCIPLINES = ['classic', 'beach'];
    public const QUESTIONS_COUNT = 12;
    public const MAX_SCORE = 36;

    /** По возрастанию. */
    public const LEVELS = ['beginner', 'beginner_plus', 'middle_minus', 'middle'];

    /** @return list<array{title:string,options:list<string>}> */
    public function questions(string $discipline, ?string $locale = null): array
    {
        $this->assertDiscipline($discipline);

        $list = trans("leveltest.{$discipline}_questions", [], $locale);

        return array_values(array_map(fn ($q) => [
            'title' => (string) $q['title'],
            'options' => array_values(array_map('strval', $q['options'])),
        ], (array) $list));
    }

    /**
     * @param  array<int,int|string>  $answers  12 значений 0..3 в порядке вопросов
     * @return array{level:string,score:int,max:int,capped:bool}
     */
    public function evaluate(string $discipline, array $answers): array
    {
        $this->assertDiscipline($discipline);

        $answers = array_values($answers);
        if (count($answers) !== self::QUESTIONS_COUNT) {
            throw new InvalidArgumentException('Expected 12 answers');
        }
        foreach ($answers as $a) {
            if (!is_numeric($a) || (int) $a < 0 || (int) $a > 3 || (string) (int) $a !== (string) $a) {
                throw new InvalidArgumentException('Answer must be 0..3');
            }
        }
        $a = array_map('intval', $answers);
        $score = array_sum($a);

        $idx = match (true) {
            $score <= 8 => 0,
            $score <= 17 => 1,
            $score <= 26 => 2,
            default => 3,
        };

        $maxIdx = 3;
        if ($discipline === 'classic') {
            // номера вопросов 1-based → индексы 0-based
            if ($a[8] < 3 || $a[7] < 3) {
                $maxIdx = min($maxIdx, 2);
            }
            if ($a[2] < 2 || $a[9] < 2) {
                $maxIdx = min($maxIdx, 2);
            }
        } else {
            $weak = 0;
            foreach ([1, 2, 3, 5] as $i) { // вопросы 2, 3, 4, 6
                if ($a[$i] <= 2) {
                    $weak++;
                }
            }
            if ($weak >= 2) {
                $maxIdx = min($maxIdx, 2);
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
