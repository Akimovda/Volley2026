<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfilePhotoService
{
    /**
     * Скачиваем аватар провайдера ТОЛЬКО если у пользователя ещё нет profile_photo_path.
     *
     * Сохраняем:
     * - original: storage/app/public/profile-photos/original/{userId}/...
     * - thumb:    storage/app/public/profile-photos/thumb/{userId}/... (250x250, JPG)
     *
     * Возвращаем path для users.profile_photo_path (thumb path) или null.
     */
    public static function storeProviderAvatarIfMissing(
        int $userId,
        ?string $avatarUrl,
        ?string $currentProfilePhotoPath
    ): ?string {
        if ($userId <= 0) return null;
        if (!empty($currentProfilePhotoPath)) return null;
        if (empty($avatarUrl)) return null;

        try {
            $resp = Http::timeout(10)
                ->withHeaders(['User-Agent' => 'VolleyBot/1.0'])
                ->get($avatarUrl);

            if (!$resp->ok()) return null;

            $contentType = (string) $resp->header('Content-Type');
            if (!self::isAllowedImageContentType($contentType)) {
                return null;
            }

            $bytes = $resp->body();
            if (empty($bytes)) return null;

            // Защита от “слишком больших” картинок (подстрой лимит при необходимости)
            if (strlen($bytes) > 10 * 1024 * 1024) { // 10MB
                return null;
            }

            $ext = self::guessExtension($contentType) ?? 'jpg';
            $baseName = (string) Str::uuid();

            $originalPath = "profile-photos/original/{$userId}/{$baseName}.{$ext}";
            $thumbPath    = "profile-photos/thumb/{$userId}/{$baseName}.jpg"; // thumb всегда JPG

            // 1) original
            Storage::disk('public')->put($originalPath, $bytes);

            // 2) thumb
            $thumbJpg = self::makeThumb250Jpg($bytes);
            if (empty($thumbJpg)) {
                // если thumb не сделали — убираем original, чтобы не оставлять мусор
                Storage::disk('public')->delete($originalPath);
                return null;
            }

            Storage::disk('public')->put($thumbPath, $thumbJpg);

            return $thumbPath;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function isAllowedImageContentType(?string $contentType): bool
    {
        $ct = strtolower((string) $contentType);
        return str_contains($ct, 'image/jpeg')
            || str_contains($ct, 'image/png')
            || str_contains($ct, 'image/webp')
            || str_contains($ct, 'image/gif');
    }

    private static function guessExtension(?string $contentType): ?string
    {
        $ct = strtolower((string) $contentType);
        if (str_contains($ct, 'image/jpeg')) return 'jpg';
        if (str_contains($ct, 'image/png'))  return 'png';
        if (str_contains($ct, 'image/webp')) return 'webp';
        if (str_contains($ct, 'image/gif'))  return 'gif';
        return null;
    }

    /**
     * Делает квадратный thumb 250x250 по центру.
     * Поддерживает JPG/PNG/WEBP/GIF через imagecreatefromstring (если GD умеет).
     * Возвращает JPG bytes (качество 85) или null.
     *
     * Важно: для PNG/WebP с прозрачностью — кладём на белый фон.
     */
    private static function makeThumb250Jpg(string $bytes): ?string
    {
        $src = @imagecreatefromstring($bytes);
        if (!$src) return null;

        $w = imagesx($src);
        $h = imagesy($src);
        if ($w <= 0 || $h <= 0) {
            imagedestroy($src);
            return null;
        }

        $side = min($w, $h);
        $srcX = (int) floor(($w - $side) / 2);
        $srcY = (int) floor(($h - $side) / 2);

        $dst = imagecreatetruecolor(250, 250);

        // белый фон (важно для прозрачных PNG/WebP -> JPG)
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefilledrectangle($dst, 0, 0, 249, 249, $white);

        imagecopyresampled($dst, $src, 0, 0, $srcX, $srcY, 250, 250, $side, $side);

        ob_start();
        imagejpeg($dst, null, 85);
        $out = ob_get_clean();

        imagedestroy($src);
        imagedestroy($dst);

        return $out ?: null;
    }
}
