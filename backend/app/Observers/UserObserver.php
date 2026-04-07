<?php

namespace App\Observers;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class UserObserver
{
    public function created(User $user): void
    {
        $this->assignAnimalAvatar($user);
    }

    private function assignAnimalAvatar(User $user): void
    {
        // Если уже есть фото — не трогаем
        if ($user->getMedia('photos')->isNotEmpty()) {
            return;
        }

        $dir = public_path('img/avatars/animals');
        $files = glob($dir . '/*.{png,jpg,jpeg,webp}', GLOB_BRACE);

        if (empty($files)) {
            return;
        }

        $file = $files[array_rand($files)];

        try {
            $media = $user->addMedia($file)
                ->preservingOriginal()
                ->toMediaCollection('photos');

            // Устанавливаем как аватар
            $user->avatar_media_id = $media->id;
            $user->saveQuietly();
        } catch (\Throwable $e) {
            Log::warning("UserObserver: failed to assign animal avatar to user #{$user->id}: " . $e->getMessage());
        }
    }
}
