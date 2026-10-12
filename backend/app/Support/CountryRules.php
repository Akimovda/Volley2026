<?php

namespace App\Support;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

/**
 * Правила ФИО и телефона в зависимости от страны пользователя.
 * Для стран из CYRILLIC_NAME_COUNTRIES поведение прежнее (кириллица + автотранслит),
 * для остальных — любые буквы Unicode без перевода.
 */
class CountryRules
{
    public const DEFAULT_COUNTRY = 'RU';

    /** Страны, где ФИО по-прежнему только кириллицей (латиница автоматически переводится). */
    public const CYRILLIC_NAME_COUNTRIES = ['RU', 'KZ', 'UZ'];

    /** Страны, доступные для выбора в профиле. */
    public const SUPPORTED = [
        'RU', 'KZ', 'UZ',
        'GE', 'AM', 'AE', 'TR', 'US', 'ID', 'TH',
        // Европа
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IS', 'IE', 'IT',
        'LV', 'LT', 'LU', 'MT', 'ME', 'NL', 'NO', 'PL', 'PT', 'RO', 'RS', 'SK', 'SI', 'ES', 'SE', 'CH', 'GB',
    ];

    public static function normalizeCountry(?string $code): string
    {
        $code = strtoupper(trim((string) $code));
        return in_array($code, self::SUPPORTED, true) ? $code : self::DEFAULT_COUNTRY;
    }

    public static function usesCyrillicNames(?string $country): bool
    {
        return in_array(self::normalizeCountry($country), self::CYRILLIC_NAME_COUNTRIES, true);
    }

    /** Regex для ФИО (используется в validate()). */
    public static function nameRegex(?string $country): string
    {
        return self::usesCyrillicNames($country)
            ? '/^[А-Яа-яЁё \-\'’]+$/u'
            : '/^\p{L}[\p{L}\p{M} \-\'’.]*$/u';
    }

    public static function nameMessage(?string $country, string $label): string
    {
        return self::usesCyrillicNames($country)
            ? "{$label} должно быть на кириллице (разрешены пробел/дефис)."
            : "{$label}: допустимы только буквы, пробел, дефис и апостроф.";
    }

    /** Нормализация одного компонента ФИО. Для кириллических стран — вызывающий код делает транслит сам. */
    public static function normalizeLatinName(?string $value): ?string
    {
        if ($value === null) return null;

        $s = trim(preg_replace('/\s+/u', ' ', $value));
        $s = str_replace('’', "'", $s);
        $s = preg_replace("/-{2,}/u", '-', $s);
        $s = preg_replace("/[^\p{L}\p{M} \-'.]/u", '', $s);
        if ($s === '') return null;

        $parts = preg_split("/(\s+|-|')/u", $s, -1, PREG_SPLIT_DELIM_CAPTURE);
        $out = '';
        foreach ($parts as $p) {
            if ($p === '' || preg_match("/^(\s+|-|')$/u", $p)) {
                $out .= $p;
                continue;
            }
            // Смешанный регистр («McDonald», «DeLuca») не трогаем; ALL CAPS / all lower — приводим.
            if ($p === mb_strtolower($p, 'UTF-8') || $p === mb_strtoupper($p, 'UTF-8')) {
                $p = mb_strtoupper(mb_substr($p, 0, 1, 'UTF-8'), 'UTF-8')
                    . mb_strtolower(mb_substr($p, 1, null, 'UTF-8'), 'UTF-8');
            }
            $out .= $p;
        }

        $out = trim($out);
        return $out === '' ? null : $out;
    }

    // ---------------- Телефон ----------------

    /**
     * Приводит ввод к E.164. Для RU — прежняя логика (8XXXXXXXXXX / 10 цифр → +7…).
     * Для остальных — разбор libphonenumber с регионом страны по умолчанию.
     * Нераспознанное возвращается как '+цифры' — валидатор его отклонит.
     */
    public static function normalizePhone(?string $raw, ?string $country): ?string
    {
        if ($raw === null) return null;
        $digits = preg_replace('/\D+/', '', $raw);
        if ($digits === '') return null;

        $country = self::normalizeCountry($country);

        if ($country === 'RU') {
            if (strlen($digits) === 11 && str_starts_with($digits, '8')) {
                $digits = '7' . substr($digits, 1);
            }
            if (strlen($digits) === 11 && str_starts_with($digits, '7')) return '+' . $digits;
            if (strlen($digits) === 10) return '+7' . $digits;
            return '+' . $digits;
        }

        $util = PhoneNumberUtil::getInstance();
        try {
            $hasPlus = str_starts_with(trim($raw), '+');
            $num = $util->parse($hasPlus ? '+' . $digits : $raw, $hasPlus ? null : $country);
            return $util->format($num, PhoneNumberFormat::E164);
        } catch (NumberParseException) {
            return '+' . $digits;
        }
    }

    public static function isValidPhone(?string $e164, ?string $country): bool
    {
        if ($e164 === null || $e164 === '') return true;

        if (self::normalizeCountry($country) === 'RU') {
            return (bool) preg_match('/^\+7\d{10}$/', $e164);
        }

        try {
            $util = PhoneNumberUtil::getInstance();
            return $util->isValidNumber($util->parse($e164, null));
        } catch (NumberParseException) {
            return false;
        }
    }

    /** Для телефонов организаций (школа): +7 — строго 10 цифр, остальные — любой валидный международный номер. */
    public static function isValidAnyPhone(?string $e164): bool
    {
        if ($e164 === null || $e164 === '') return true;
        if (str_starts_with($e164, '+7')) return (bool) preg_match('/^\+7\d{10}$/', $e164);
        return self::isValidPhone($e164, 'US'); // не-RU: проверка libphonenumber (регион не используется — номер в формате +код)
    }

    /** Терминология уровней: 'intl' для стран вне RU/KZ/UZ (без российских разрядов), иначе null — решает регион. */
    public static function levelScopeForCountry(?string $country): ?string
    {
        $country = strtoupper(trim((string) $country));
        if ($country === '' || in_array($country, self::CYRILLIC_NAME_COUNTRIES, true)) {
            return null;
        }
        return 'intl';
    }

    /**
     * Ключ для поиска дублей по ФИО независимо от алфавита и порядка слов:
     * «Иван Петров» / «Ivan Petrov» / «Petrov Ivan» дают один ключ. Нестрогий (для подсказки админу).
     */
    public static function looseNameKey(?string $first, ?string $last): string
    {
        $s = \Illuminate\Support\Str::ascii(mb_strtolower(trim((string) $first . ' ' . (string) $last)));
        $words = preg_split('/[^a-z]+/', strtolower($s), -1, PREG_SPLIT_NO_EMPTY);
        $words = array_map(function ($w) {
            $w = strtr($w, ['kh' => 'h', 'zh' => 'z', 'sh' => 's', 'ch' => 'c', 'ts' => 'c', 'ph' => 'f', 'x' => 'ks', 'w' => 'v', 'y' => 'i', 'j' => 'i', 'q' => 'k']);
            return preg_replace('/(.)\1+/', '$1', $w);
        }, $words);
        sort($words);
        return implode(' ', $words);
    }

    public static function phoneMessage(?string $country): string
    {
        return self::normalizeCountry($country) === 'RU'
            ? 'Телефон должен быть в формате +7XXXXXXXXXX (11 цифр после +7).'
            : 'Укажите корректный номер телефона в международном формате (например, +995 555 12 34 56).';
    }
}
