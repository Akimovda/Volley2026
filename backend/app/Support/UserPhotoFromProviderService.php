<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class UserPhotoFromProviderService
{
    /**
     * Политика:
     * - если новый пользователь (регистрация) -> добавляем фото в галерею и ставим аватар
     * - если существующий -> добавляем ТОЛЬКО если галерея и avatar пустые
     */
    public static function seedFromProviderIfAllowed(User $user, ?string $avatarUrl, bool $isNewUser): void
    {
        $avatarUrl = is_string($avatarUrl) ? trim($avatarUrl) : '';
        if ($avatarUrl === '') {
            return;
        }

        $hasAvatar = (bool) $user->getFirstMedia('avatar');
        $hasPhotos = $user->getMedia('photos')->isNotEmpty();

        if (!$isNewUser && ($hasAvatar || $hasPhotos)) {
            return;
        }

        try {
            $photo = $user->addMediaFromUrl($avatarUrl)->toMediaCollection('photos');

            if (!$user->getFirstMedia('avatar') && $photo) {
                $user->addMedia($photo->getPath())->toMediaCollection('avatar');
            }
        } catch (\Throwable $e) {
            Log::warning('Provider avatar seed failed', [
                'user_id' => (int) $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
