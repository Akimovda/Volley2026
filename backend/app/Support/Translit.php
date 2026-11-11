<?php

namespace App\Support;

/**
 * Транслитерация имени для карточки абонемента «как на банковской карте» (как у российских банков, Visa/Mastercard):
 * «Акимов Дмитрий» → «DMITRIY AKIMOV». Уже латинские имена просто приводятся к верхнему регистру.
 */
class Translit
{
    private const MAP = [
        'а' => 'A', 'б' => 'B', 'в' => 'V', 'г' => 'G', 'д' => 'D', 'е' => 'E', 'ё' => 'E', 'ж' => 'ZH', 'з' => 'Z',
        'и' => 'I', 'й' => 'Y', 'к' => 'K', 'л' => 'L', 'м' => 'M', 'н' => 'N', 'о' => 'O', 'п' => 'P', 'р' => 'R',
        'с' => 'S', 'т' => 'T', 'у' => 'U', 'ф' => 'F', 'х' => 'KH', 'ц' => 'TS', 'ч' => 'CH', 'ш' => 'SH', 'щ' => 'SHCH',
        'ъ' => '', 'ы' => 'Y', 'ь' => '', 'э' => 'E', 'ю' => 'YU', 'я' => 'YA',
    ];

    public static function toLatin(string $text): string
    {
        $out = '';
        foreach (preg_split('//u', mb_strtolower(trim($text)), -1, PREG_SPLIT_NO_EMPTY) as $ch) {
            $out .= self::MAP[$ch] ?? (preg_match('/[a-z0-9\- ]/', $ch) ? strtoupper($ch) : '');
        }

        return trim(preg_replace('/\s+/', ' ', $out));
    }

    /** «ИМЯ ФАМИЛИЯ» латиницей для карточки (порядок как на банковских картах). */
    public static function cardName(?object $user): string
    {
        if (!$user) {
            return '';
        }
        $first = trim((string) ($user->first_name ?? ''));
        $last  = trim((string) ($user->last_name ?? ''));
        $full  = trim($first . ' ' . $last);
        if ($full === '') {
            $full = trim((string) ($user->name ?? ''));
        }

        return mb_substr(self::toLatin($full), 0, 26);
    }
}
