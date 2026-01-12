<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class ProfilePhotoService
{
    /**
     * Скачивает аватар провайдера ТОЛЬКО если у пользователя ещё нет profile_photo_path.
     *
     * Структура хранения (disk=public):
     *  - avatars/original/{userId}/av-{userId}-{ts}-{rand}.{ext}
     *  - avatars/thumbs/{userId}/av-{userId}-{ts}-{rand}.jpg   (квадрат 250x250)
     *
     * Возвращает путь, который можно положить в users.profile_photo_path (thumb).
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
            $resp = Http::timeout(10)->get($avatarUrl);
            if (!$resp->ok()) return null;

            $bytes = (string) $resp->body();
            if ($bytes === '') return null;

            // Определяем формат по содержимому/заголовку
            $ext = self::guessExtension($resp->header('Content-Type'), $bytes) ?? 'jpg';

            // Короткое имя файла: av-45-20260112153010-1234
            $base = self::makeBaseName($userId);

            $originalPath = "avatars/original/{$userId}/{$base}.{$ext}";
            $thumbPath    = "avatars/thumbs/{$userId}/{$base}.jpg";

            // 1) оригинал
            Storage::disk('public')->put($originalPath, $bytes);

            // 2) thumb 250x250 (JPG)
            $thumbJpg = self::makeThumb250Jpg($bytes);
            if (empty($thumbJpg)) return null;

            Storage::disk('public')->put($thumbPath, $thumbJpg);

            return $thumbPath;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function makeBaseName(int $userId): string
    {
        $ts = now()->format('YmdHis');
        $rand = random_int(1000, 9999);
        return "av-{$userId}-{$ts}-{$rand}";
    }

    private static function guessExtension(?string $contentType, string $bytes): ?string
    {
        $ct = strtolower((string) $contentType);

        if (str_contains($ct, 'image/jpeg')) return 'jpg';
        if (str_contains($ct, 'image/png'))  return 'png';
        if (str_contains($ct, 'image/webp')) return 'webp';

        // fallback по фактическому mime
        $info = @getimagesizefromstring($bytes);
        $mime = strtolower((string)($info['mime'] ?? ''));

        if ($mime === 'image/jpeg') return 'jpg';
        if ($mime === 'image/png')  return 'png';
        if ($mime === 'image/webp') return 'webp';

        return null;
    }

    /**
     * Делает квадратный thumb 250x250 из JPG/PNG/WEBP (если GD поддерживает).
     * Возвращает JPG bytes.
     */
    private static function makeThumb250Jpg(string $bytes): ?string
    {
        $im = @imagecreatefromstring($bytes);
        if (!$im) return null;

        $w = imagesx($im);
        $h = imagesy($im);
        if ($w <= 0 || $h <= 0) {
            imagedestroy($im);
            return null;
        }

        // crop center square
        $side = min($w, $h);
        $srcX = (int) floor(($w - $side) / 2);
        $srcY = (int) floor(($h - $side) / 2);

        $dst = imagecreatetruecolor(250, 250);
        imagecopyresampled($dst, $im, 0, 0, $srcX, $srcY, 250, 250, $side, $side);

        ob_start();
        imagejpeg($dst, null, 85);
        $out = ob_get_clean();

        imagedestroy($im);
        imagedestroy($dst);

        return $out ?: null;
    }
}
