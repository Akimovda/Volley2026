<?php

namespace App\Support;

/**
 * Поиск по именам без различия «ё» и «е» (Артём = Артем, Алёна = Алена).
 * Обе стороны сравнения приводятся к «е»: колонка/выражение — через translate() в SQL, запрос — в PHP.
 */
final class NameSearch
{
    /** Текст запроса (в т.ч. шаблон LIKE) без «ё». */
    public static function text(string $value): string
    {
        return str_replace(['ё', 'Ё'], ['е', 'Е'], $value);
    }

    /** SQL-выражение: колонка или выражение с заменой «ё» на «е». */
    public static function col(string $expr): string
    {
        return "translate({$expr}, 'ёЁ', 'еЕ')";
    }

    /** Условие для whereRaw: «выражение ILIKE ?»; параметр — NameSearch::text($like). */
    public static function ilike(string $expr): string
    {
        return self::col($expr) . ' ILIKE ?';
    }
}
