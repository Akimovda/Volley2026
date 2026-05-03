<?php
declare(strict_types=1);

namespace App\Support;

class AssetVersion
{
    /**
     * URL ассета с версией на основе mtime файла.
     * Меняется только при реальном изменении файла, стабилен между запросами.
     */
    public static function url(string $path): string
    {
        $path = ltrim($path, '/');
        $absolute = public_path($path);

        if (!is_file($absolute)) {
            return '/' . $path;
        }

        return '/' . $path . '?v=' . filemtime($absolute);
    }
}
