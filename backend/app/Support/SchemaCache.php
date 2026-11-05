<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Кэшированные проверки наличия таблицы/колонки. Schema::hasColumn() на PostgreSQL каждый раз выполняет запрос к каталогу
 * (~7 мс), а в ленте /events таких вызовов было ~30 за запрос (≈200 мс). Результат держим в памяти процесса и в кэше (1 час);
 * после миграций кэш сбрасывается (слушатель MigrationsEnded в AppServiceProvider).
 */
final class SchemaCache
{
    private const TTL = 3600;

    /** @var array<string, bool> */
    private static array $memo = [];
    private static ?string $version = null;

    public static function hasTable(string $table): bool
    {
        return self::remember('t:' . $table, fn () => Schema::hasTable($table));
    }

    public static function hasColumn(string $table, string $column): bool
    {
        return self::remember('c:' . $table . '.' . $column, fn () => Schema::hasColumn($table, $column));
    }

    /** Сброс (после миграций): новая версия ключей. */
    public static function flush(): void
    {
        self::$memo = [];
        self::$version = null;
        try {
            Cache::forever('schema_cache:version', (string) microtime(true));
        } catch (\Throwable $e) {
            // кэш недоступен — проверки и так идут напрямую
        }
    }

    private static function remember(string $key, \Closure $check): bool
    {
        if (isset(self::$memo[$key])) {
            return self::$memo[$key];
        }
        try {
            self::$version ??= (string) Cache::get('schema_cache:version', '0');

            return self::$memo[$key] = (bool) Cache::remember('schema_cache:' . self::$version . ':' . $key, self::TTL, fn () => (int) $check());
        } catch (\Throwable $e) {
            return self::$memo[$key] = (bool) $check();
        }
    }
}
