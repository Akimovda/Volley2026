<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Brand;
use App\Models\LevelScheme;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Свои названия и цвета 7 уровней. Цепочка: схема организатора (школы) → схема бренда приложения → null
 * (дальше level_name() работает как раньше: standard/spb). Схема принимается ТОЛЬКО целиком (ровно 7 уровней).
 */
class LevelLabelService
{
    public const LEVELS = 7;

    private static array $memo = [];

    /** @return array<int,array{name_ru:string,name_en:?string,short_ru:?string,short_en:?string,color:?string,text_color:?string}>|null */
    public static function scheme(string $type, int $ownerId): ?array
    {
        $k = $type . ':' . $ownerId;
        if (!array_key_exists($k, self::$memo)) {
            self::$memo[$k] = Cache::rememberForever('level_scheme.' . $k, function () use ($type, $ownerId) {
                $rows = LevelScheme::query()->where('owner_type', $type)->where('owner_id', $ownerId)->orderBy('level')->get();
                if ($rows->count() !== self::LEVELS) {
                    return null;
                }

                return $rows->keyBy('level')->map(fn ($r) => $r->only(['name_ru', 'name_en', 'short_ru', 'short_en', 'color', 'text_color']))->all();
            });
        }

        return self::$memo[$k];
    }

    /** Эффективная схема: организатор → текущий бренд. */
    public static function resolve(?int $organizerId = null): ?array
    {
        if ($organizerId && ($s = self::scheme('organizer', $organizerId))) {
            return $s;
        }
        $brand = app()->bound(Brand::class) ? app(Brand::class) : null;

        return $brand?->id ? self::scheme('brand', (int) $brand->id) : null;
    }

    public static function name(int $level, ?int $organizerId, bool $short = false): ?string
    {
        $row = self::resolve($organizerId)[$level] ?? null;
        if (!$row) {
            return null;
        }
        $en = app()->getLocale() === 'en';
        $full = ($en ? $row['name_en'] : null) ?: $row['name_ru'];
        if (!$short) {
            return $full;
        }

        return ($en ? $row['short_en'] : null) ?: $row['short_ru'] ?: $full;
    }

    /** Inline-style пилюли уровня ('' если своей схемы нет или цвет не задан). */
    public static function pillStyle(int $level, ?int $organizerId): string
    {
        $row = self::resolve($organizerId)[$level] ?? null;
        if (!$row || empty($row['color'])) {
            return '';
        }

        return 'background:' . $row['color'] . ';color:' . ($row['text_color'] ?: '#fff') . ';text-shadow:none;border-color:' . $row['color'];
    }

    /** Сохранить схему целиком; $rows = [1..7 => [...]]. Пустой набор — удалить схему. */
    public static function save(string $type, int $ownerId, array $rows): void
    {
        DB::transaction(function () use ($type, $ownerId, $rows) {
            LevelScheme::query()->where('owner_type', $type)->where('owner_id', $ownerId)->delete();
            foreach ($rows as $level => $r) {
                LevelScheme::create([
                    'owner_type' => $type, 'owner_id' => $ownerId, 'level' => (int) $level,
                    'name_ru' => $r['name_ru'], 'name_en' => $r['name_en'] ?: null,
                    'short_ru' => $r['short_ru'] ?: null, 'short_en' => $r['short_en'] ?: null,
                    'color' => $r['color'] ?: null, 'text_color' => $r['text_color'] ?: null,
                ]);
            }
        });
        Cache::forget("level_scheme.$type:$ownerId");
        unset(self::$memo["$type:$ownerId"]);
    }

    /** Данные для API приложения. */
    public static function payload(?int $organizerId): array
    {
        $scheme = self::resolve($organizerId);
        $levels = [];
        for ($l = 1; $l <= self::LEVELS; $l++) {
            $r = $scheme[$l] ?? null;
            $levels[] = [
                'level' => $l,
                'name_ru' => $r['name_ru'] ?? level_name($l),
                'name_en' => $r['name_en'] ?? null,
                'short_ru' => $r['short_ru'] ?? null,
                'short_en' => $r['short_en'] ?? null,
                'color' => $r['color'] ?? level_color($l),
                'text_color' => $r['text_color'] ?? null,
            ];
        }

        return ['custom' => (bool) $scheme, 'levels' => $levels];
    }
}
