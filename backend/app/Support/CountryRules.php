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
        'GE', 'AM', 'AE', 'TR', 'US', 'ID', 'TH', 'VN', 'EG',
        // Европа
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IS', 'IE', 'IT',
        'LV', 'LT', 'LU', 'MT', 'ME', 'NL', 'NO', 'PL', 'PT', 'RO', 'RS', 'SK', 'SI', 'ES', 'SE', 'CH', 'GB',
    ];

    /** Дополнительные написания стран для поиска города (помимо названий из lang/ru и lang/en). */
    public const SEARCH_SYNONYMS = [
        'TR' => ['turkey', 'turkiye', 'турция'],
        'TH' => ['тайланд', 'thailand', 'siam'],
        'VN' => ['viet nam', 'vietnam', 'вьетнам'],
        'EG' => ['egypt', 'misr', 'masr', 'египет'],
        'US' => ['usa', 'сша', 'америка', 'штаты'],
        'GB' => ['uk', 'england', 'англия', 'великобритания'],
        'AE' => ['uae', 'оаэ', 'эмираты', 'дубай'],
        'ID' => ['indonesia', 'индонезия'],   // «Бали» — не синоним страны: острова ищутся по алиасам и региону
        'DE' => ['germany', 'германия', 'deutschland'],
        'CZ' => ['czechia', 'чехия'],
        'CN' => ['china', 'китай', 'кнр', 'prc'],
        'KR' => ['korea', 'южная корея', 'корея'],
        'JP' => ['japan', 'япония'],
        'IN' => ['india', 'индия'],
        'MV' => ['maldives', 'мальдивы'],
        'LK' => ['sri lanka', 'шри-ланка', 'цейлон'],
        'DO' => ['доминикана', 'dominicana', 'dominican republic', 'доминиканская республика'],
        'MX' => ['mexico', 'мексика'],
        'CU' => ['cuba', 'куба'],
        'GR' => ['greece', 'греция', 'hellas'],
        'CY' => ['cyprus', 'кипр'],
    ];

    /** Нижний регистр, ё→е; латиница — без диакритики (Türkiye → turkiye). Кириллицу не транслитерируем. */
    public static function searchNorm(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = str_replace('ё', 'е', $s);
        if (!preg_match('/\p{Cyrillic}/u', $s)) {
            $s = \Illuminate\Support\Str::ascii($s);
        }
        return $s;
    }

    /**
     * Страны как данные (таблица countries, миграция 2026_11_04_170000): код => [ru, en, alt_ru, alt_en, active].
     * Пока таблицы нет (до миграции) — только исходные SUPPORTED.
     */
    private static function map(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('countries_map_v1', 300, function () {
            $map = [];
            try {
                if (\App\Support\SchemaCache::hasTable('countries')) {
                    foreach (\Illuminate\Support\Facades\DB::table('countries')->get() as $r) {
                        $map[$r->code] = [$r->name_ru, $r->name_en, $r->alt_ru, $r->alt_en, (bool) $r->is_active];
                    }
                }
            } catch (\Throwable $e) {
                // БД недоступна — работаем с исходным списком
            }
            foreach (self::SUPPORTED as $code) {
                $map[$code] = ($map[$code] ?? [$code, $code, null, null, true]);
                $map[$code][4] = true;
            }
            return $map;
        });
    }

    /** Поддерживаемые страны: исходные SUPPORTED + включённые автоматически (countries.is_active). */
    public static function supported(): array
    {
        return array_values(array_keys(array_filter(self::map(), fn ($r) => $r[4])));
    }

    /** Код есть в справочнике стран мира (можно добавлять города). */
    public static function isKnown(?string $code): bool
    {
        return $code !== null && isset(self::map()[strtoupper(trim($code))]);
    }

    /** Включить страну (после добавления в ней первого города): она появляется в профиле и в поиске городов. */
    public static function activate(string $code): void
    {
        $code = strtoupper(trim($code));
        if (!self::isKnown($code)) {
            return;
        }
        \Illuminate\Support\Facades\DB::table('countries')->where('code', $code)->update(['is_active' => true, 'updated_at' => now()]);
        \Illuminate\Support\Facades\Cache::forget('countries_map_v1');
    }

    /** Название страны: сначала lang/{locale}/countries.php (для исходных — своё, напр. «Индонезия (Бали)»), затем справочник. */
    public static function countryName(string $code, ?string $locale = null): string
    {
        $code = strtoupper($code);
        $locale = $locale ?: app()->getLocale();
        $key = 'countries.' . $code;
        $t = trans($key, [], $locale);
        if (is_string($t) && $t !== $key) {
            return $t;
        }
        $row = self::map()[$code] ?? null;
        if ($row) {
            return $locale === 'ru' ? $row[0] : $row[1];
        }
        return $code;
    }

    /** Все названия страны для поиска (ru, en, короткие, синонимы). */
    private static function namesFor(string $code): array
    {
        $row = self::map()[$code] ?? [null, null, null, null, false];
        $names = [
            trans("countries.$code", [], 'ru'),
            trans("countries.$code", [], 'en'),
            $row[0], $row[1], $row[2], $row[3],
            ...(self::SEARCH_SYNONYMS[$code] ?? []),
        ];
        // Пояснение в скобках — не название страны («Индонезия (Бали)» → «Индонезия»; «Макао (САР)» → «Макао»):
        // иначе запрос «Бали» возвращал бы все города страны.
        $names = array_map(fn ($n) => is_string($n) ? trim(preg_replace('/\s*\(.*?\)/u', '', $n)) : $n, $names);
        return array_values(array_filter($names, fn ($n) => is_string($n) && $n !== '' && $n !== "countries.$code"));
    }

    /** Список для выбора страны в виджете «+ Добавить город»: все страны мира, сортировка по названию. */
    public static function pickerList(?string $locale = null): array
    {
        $locale = $locale ?: app()->getLocale();
        $list = [];
        foreach (array_keys(self::map()) as $code) {
            $name = self::countryName($code, $locale);
            $alt = array_values(array_unique(array_filter(self::namesFor($code), fn ($n) => $n !== $name)));
            $list[] = ['code' => $code, 'name' => $name, 'alt' => implode(' | ', $alt)];
        }
        usort($list, fn ($a, $b) => strcmp(mb_strtolower($a['name']), mb_strtolower($b['name'])));
        return $list;
    }

    /**
     * Коды поддерживаемых стран, чьё название (ru/en, короткие, синонимы) содержит запрос.
     * Для запросов короче 3 символов не используется (слишком много случайных совпадений).
     */
    public static function codesMatchingName(string $query): array
    {
        $q = self::searchNorm($query);
        if (mb_strlen($q) < 3) {
            return [];
        }
        $codes = [];
        foreach (self::supported() as $code) {
            foreach (self::namesFor($code) as $n) {
                if (str_contains(self::searchNorm($n), $q)) {
                    $codes[] = $code;
                    break;
                }
            }
        }
        return $codes;
    }

    public static function normalizeCountry(?string $code): string
    {
        $code = strtoupper(trim((string) $code));
        return in_array($code, self::supported(), true) ? $code : self::DEFAULT_COUNTRY;
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

    /** $field: first | last | patronym */
    public static function nameMessage(?string $country, string $field): string
    {
        return self::usesCyrillicNames($country)
            ? __('profile.err_' . $field . '_cyr')
            : __('profile.err_name_latin', ['label' => __('profile.cp_lbl_' . ($field === 'patronym' ? 'patronym' : $field))]);
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

    /** Пример мобильного номера страны в международном формате (из libphonenumber), напр. «+995 555 12 34 56». */
    public static function phoneExample(?string $country): string
    {
        $country = self::normalizeCountry($country);
        try {
            $util = PhoneNumberUtil::getInstance();
            $num = $util->getExampleNumberForType($country, \libphonenumber\PhoneNumberType::MOBILE) ?? $util->getExampleNumber($country);
            return $num ? $util->format($num, PhoneNumberFormat::INTERNATIONAL) : '+' . $util->getCountryCodeForRegion($country);
        } catch (\Throwable) {
            return '+';
        }
    }

    public static function phoneMessage(?string $country): string
    {
        return __('profile.err_phone', ['example' => self::phoneExample($country)]);
    }
}
