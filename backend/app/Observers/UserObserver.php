<?php

namespace App\Observers;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserObserver
{
    public function created(User $user): void
    {
        $this->assignAnimalAvatar($user);
    }

    /**
     * Страховка: при soft-delete пользователя отменяем все его активные регистрации.
     * AccountDeleteRequestController и UserMergeService делают это явно, но иногда
     * остаются сиротские записи (напр. race condition при merge). Observer — последняя линия.
     */
    public function deleting(User $user): void
    {
        $remaining = DB::table('event_registrations')
            ->where('user_id', $user->id)
            ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
            ->whereNull('cancelled_at')
            ->count();

        if ($remaining > 0) {
            DB::table('event_registrations')
                ->where('user_id', $user->id)
                ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
                ->whereNull('cancelled_at')
                ->update([
                    'is_cancelled' => true,
                    'cancelled_at' => now(),
                    'status'       => 'cancelled',
                    'updated_at'   => now(),
                ]);

            Log::info("UserObserver::deleting — отменено {$remaining} регистраций для user #{$user->id}");
        }
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
