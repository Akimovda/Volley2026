<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * ProfilePhotoService
 *
 * ЕДИНЫЙ контракт для всех провайдеров (Telegram/VK/Yandex):
 * - сохраняем оригинал в:  storage/app/public/avatars/original/{userId}/av-{userId}.{ext}
 * - сохраняем thumb в:     storage/app/public/avatars/thumbs/{userId}/av-{userId}.jpg (250x250)
 * - в БД пишем ТОЛЬКО basename без расширения: "av-{userId}"
 *
 * Важно: Этот сервис НЕ решает "как собирать URL" — это делается в User::getProfilePhotoUrlAttribute().
 */
class ProfilePhotoService
{
    /**
     * Скачиваем и сохраняем аватар провайдера ТОЛЬКО если у пользователя ещё нет profile_photo_path.
     *
     * @return string|null  Возвращает basename для БД: "av-{userId}" или null если ничего не сохранили
     */
    public static function storeProviderAvatarBasenameIfMissing(
        int $userId,
        ?string $avatarUrl,
        ?string $currentProfilePhotoPath
    ): ?string {
        if (!empty($currentProfilePhotoPath)) return null;
        if (empty($avatarUrl)) return null;

        try {
            $resp = Http::timeout(10)->get($avatarUrl);
            if (!$resp->ok()) return null;

            $bytes = (string) $resp->body();
            if ($bytes === '') return null;

            $contentType = $resp->header('Content-Type');
            $ext = self::guessExtension($contentType) ?? self::guessExtensionFromBytes($bytes) ?? 'jpg';

            $baseName = "av-{$userId}";

            $originalPath = "avatars/original/{$userId}/{$baseName}.{$ext}";
            $thumbPath    = "avatars/thumbs/{$userId}/{$baseName}.jpg";

            // original
            Storage::disk('public')->put($originalPath, $bytes);

            // thumb 250x250 jpg
            $thumbJpg = self::makeThumb250($bytes);
            if (empty($thumbJpg)) return null;

            Storage::disk('public')->put($thumbPath, $thumbJpg);

            // в БД пишем ТОЛЬКО basename
            return $baseName;
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

    private static function guessExtensionFromBytes(string $bytes): ?string
    {
        // очень простой sniffing по сигнатурам
        $head = substr($bytes, 0, 12);

        // JPEG: FF D8 FF
        if (strlen($head) >= 3 && ord($head[0]) === 0xFF && ord($head[1]) === 0xD8 && ord($head[2]) === 0xFF) {
            return 'jpg';
        }

        // PNG: 89 50 4E 47 0D 0A 1A 0A
        if (strlen($head) >= 8 && $head === "\x89PNG\x0D\x0A\x1A\x0A") {
            return 'png';
        }

        // WEBP: "RIFF" .... "WEBP"
        if (strlen($head) >= 12 && substr($head, 0, 4) === 'RIFF' && substr($head, 8, 4) === 'WEBP') {
            return 'webp';
        }

        return null;
    }

    /**
     * Делает квадратный thumb 250x250. Поддерживает JPG/PNG/WEBP если GD умеет.
     * Возвращает JPG bytes.
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
        imagecopyresampled($dst, $im, 0, 0, $srcX, $srcY, 250, 250, $side, $side);

        ob_start();
        imagejpeg($dst, null, 85);
        $out = ob_get_clean();

        imagedestroy($im);
        imagedestroy($dst);

        return $out ?: null;
    }
}
