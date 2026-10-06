<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UserPhotoFromProviderService
{
    public static function seedFromProviderIfAllowed(User $user, ?string $avatarUrl, bool $isNewUser): void
    {
        $avatarUrl = is_string($avatarUrl) ? trim($avatarUrl) : '';
        if ($avatarUrl === '') {
            return;
        }

        // Сохраняем аватар ТОЛЬКО для новых пользователей
        if (!$isNewUser) {
            return;
        }

        // Заглушка Яндекса («серый силуэт», get-yapic/0/0-0) — это не фото пользователя: если у человека нет аватара,
        // Яндекс всё равно отдаёт URL заглушки. Раньше она сохранялась в галерею и ставилась аватаром
        // (в тёмной теме — «чёрная картинка с силуэтом»; так было у ~80 пользователей).
        if (self::isProviderPlaceholder($avatarUrl)) {
            return;
        }

        try {
            // Яндекс отдаёт заглушку под разными default_avatar_id (не только 0/0-0), и is_avatar_empty
            // не всегда true — поэтому для его CDN сверяем содержимое файла, а не только URL.
            $body = null;
            if (preg_match('#^https?://avatars\.yandex\.net/#i', $avatarUrl)) {
                $resp = Http::timeout(10)->get($avatarUrl);
                if (!$resp->successful()) {
                    return;
                }
                $body = $resp->body();
                if (self::isPlaceholderContent($body)) {
                    return;
                }
            }

            // Сохраняем фото в галерею
            $adder = $body !== null
                ? $user->addMediaFromString($body)->usingFileName(basename(parse_url($avatarUrl, PHP_URL_PATH) ?: 'avatar') . '.' . (['image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif'][(new \finfo(FILEINFO_MIME_TYPE))->buffer($body)] ?? 'png'))
                : $user->addMediaFromUrl($avatarUrl);
            $media = $adder
                ->preservingOriginal()
                ->toMediaCollection('photos');
            
            // Создаем thumb 480x480
            $user->addMediaConversion('thumb')
                ->fit(\Spatie\Image\Enums\Fit::Crop, 480, 480)
                ->nonQueued()
                ->performOnCollections('photos')
                ->perform();
            
            // Сохраняем ID как аватар
            $user->avatar_media_id = $media->id;
            $user->save();
            
        } catch (\Throwable $e) {
            Log::warning('Provider avatar seed failed', [
                'user_id' => (int) $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** URL заглушки аватара у провайдера (не реальное фото). */
    public static function isProviderPlaceholder(string $url): bool
    {
        // Яндекс: default_avatar_id «0/0-0»
        return (bool) preg_match('#avatars\.yandex\.net/get-yapic/0/0-0(/|$)#', $url);
    }

    /** md5 заглушки Яндекса (islands-200.png, 42×42, 1536 байт) — то же значение, что в users:cleanup-yandex-default-avatars. */
    private const PLACEHOLDER_MD5 = 'ba4fdd0fd76496bb792cb8f14067e3bd';

    public static function isPlaceholderContent(string $body): bool
    {
        return strlen($body) === 1536 && md5($body) === self::PLACEHOLDER_MD5;
    }
}
