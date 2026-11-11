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
     * Организаторам и помощникам уведомления о записях игроков включены по умолчанию: при назначении роли
     * organizer/staff (создание или смена роли) включаем notify_player_registrations. Выключить можно вручную в профиле —
     * дальше флаг не трогаем (срабатывает только в момент получения роли, и не если флаг меняется в этом же сохранении).
     */
    public function saving(User $user): void
    {
        $roles = ['organizer', 'staff'];
        if (!in_array((string) $user->role, $roles, true) || $user->isDirty('notify_player_registrations')) {
            return;
        }
        if (!$user->exists || ($user->isDirty('role') && !in_array((string) $user->getOriginal('role'), $roles, true))) {
            $user->notify_player_registrations = true;
        }
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
