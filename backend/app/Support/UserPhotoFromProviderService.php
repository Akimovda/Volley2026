<?php

namespace App\Support;

use App\Models\User;
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
            // Сохраняем фото в галерею
            $media = $user->addMediaFromUrl($avatarUrl)
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
}