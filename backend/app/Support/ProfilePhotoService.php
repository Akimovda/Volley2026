<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class ProfilePhotoService
{
    /**
     * Скачиваем аватар провайдера ТОЛЬКО если у пользователя ещё нет profile_photo_path.
     *
     * Сохраняем на диске "public":
     *  - original: avatars/original/{userId}/{uuid}.{ext}
     *  - thumbs:   avatars/thumbs/{userId}/{uuid}.jpg   (thumb всегда jpg)
     *
     * Возвращаем путь thumb, который можно положить в users.profile_photo_path
     */
    public static function storeProviderAvatarIfMissing(
        int $userId,
        ?string $avatarUrl,
        ?string $currentProfilePhotoPath,
    ): ?string {
        if (!empty($currentProfilePhotoPath)) {
            return null;
        }
        if (empty($avatarUrl)) {
            return null;
        }

        try {
            $resp = Http::timeout(10)->get($avatarUrl);
            if (!$resp->ok()) {
                return null;
            }

            $bytes = (string) $resp->body();
            if ($bytes === '') {
                return null;
            }

            $ext = self::guessExtension($resp->header('Content-Type')) ?? 'jpg';
            $uuid = (string) Str::uuid();

            $originalPath = "avatars/original/{$userId}/{$uuid}.{$ext}";
            $thumbPath    = "avatars/thumbs/{$userId}/{$uuid}.jpg";

            Storage::disk('public')->put($originalPath, $bytes);

            $thumbJpg = self::makeThumb250($bytes);
            if ($thumbJpg === null) {
                // оставим оригинал, но profile_photo_path не ставим
                return null;
            }

            Storage::disk('public')->put($thumbPath, $thumbJpg);

            return $thumbPath;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function guessExtension(?string $contentType): ?string
    {
        $ct = strtolower((string) $contentType);

        if (str_contains($ct, 'image/jpeg')) return 'jpg';
        if (str_contains($ct, 'image/png'))  return 'png';
        if (str_contains($ct, 'image/webp')) return 'webp';

        return null;
    }

    /**
     * Делает квадратный thumb 250x250 из центра. Возвращает JPEG bytes.
     * Поддержка WEBP зависит от gd_info().
     */
    private static function makeThumb250(string $bytes): ?string
    {
        $im = @imagecreatefromstring($bytes);
        if (!$im) return null;

        $w = imagesx($im);
        $h = imagesy($im);
        if ($w <= 0 || $h <= 0) {
            imagedestroy($im);
            return null;
        }

        $side = min($w, $h);
        $srcX = (int) floor(($w - $side) / 2);
        $srcY = (int) floor(($h - $side) / 2);

        $dst = imagecreatetruecolor(250, 250);

        // белый фон (на случай PNG с прозрачностью)
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefill($dst, 0, 0, $white);

        imagecopyresampled($dst, $im, 0, 0, $srcX, $srcY, 250, 250, $side, $side);

        ob_start();
        imagejpeg($dst, null, 85);
        $out = ob_get_clean();

        imagedestroy($im);
        imagedestroy($dst);

        return $out ? (string) $out : null;
    }
}
