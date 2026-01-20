<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class UserPhotoController extends Controller
{
    public function index(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $photos = $user->getMedia('photos')
            ->sortByDesc('created_at')
            ->values();

        $avatar = $user->getFirstMedia('avatar');

        return view('user.photos', [
            'user'   => $user,
            'photos' => $photos,
            'avatar' => $avatar,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'photo' => ['required', 'image', 'max:5120'], // 5MB
            // make_avatar приходит из FilePond ondata (0/1)
            'make_avatar' => ['nullable', 'boolean'],
        ]);

        /** @var User $user */
        $user = $request->user();

        // Сохраняем в коллекцию photos (диск задан в User::registerMediaCollections())
        $media = $user->addMediaFromRequest('photo')
            ->toMediaCollection('photos');

        // Если пользователь попросил "сделать аватаром" — делаем сразу,
        // иначе: если аватара ещё нет — ставим первое фото как аватар
        $makeAvatar = $request->boolean('make_avatar');
        if ($makeAvatar || !$user->getFirstMedia('avatar')) {
            $this->makeAvatarFromMedia($user, $media);
        }

        return back()->with('status', 'Фото добавлено ✅');
    }

    public function setAvatar(Request $request, Media $media)
    {
        /** @var User $user */
        $user = $request->user();

        $this->assertOwnedByUser($media, $user);

        $this->makeAvatarFromMedia($user, $media);

        return back()->with('status', 'Аватар обновлён ✅');
    }

    public function destroy(Request $request, Media $media)
    {
        /** @var User $user */
        $user = $request->user();

        $this->assertOwnedByUser($media, $user);

        $currentAvatarId = $user->getFirstMedia('avatar')?->id;
        $deletedId = (int) $media->id;

        $media->delete();

        // Если удалили фото, которое было активным аватаром — назначим fallback
        if ($currentAvatarId && (int) $currentAvatarId === $deletedId) {
            $fallback = $user->getMedia('photos')->sortByDesc('created_at')->first();
            if ($fallback) {
                $this->makeAvatarFromMedia($user, $fallback);
            }
        }

        return back()->with('status', 'Фото удалено ✅');
    }

    private function assertOwnedByUser(Media $media, User $user): void
    {
        abort_unless(
            $media->model_type === User::class && (int) $media->model_id === (int) $user->id,
            403
        );
    }

    /**
     * Ставит выбранное фото аватаром через singleFile('avatar').
     *
     * Важно:
     * - Берём локальный путь ($media->getPath()), а не URL (BasicAuth/прокси/редиректы).
     * - preservingOriginal() — чтобы исходник в photos НЕ пропадал (иначе получаются битые /storage/{id}/...).
     */
    private function makeAvatarFromMedia(User $user, Media $media): void
    {
        try {
            $path = $media->getPath();

            if (empty($path) || !is_file($path)) {
                throw new \RuntimeException('Media file not found on disk: ' . (string) $path);
            }

            $user->addMedia($path)
                ->preservingOriginal() // ключевая строка: НЕ трогаем файл из photos
                ->toMediaCollection('avatar'); // singleFile() заменит предыдущий аватар
        } catch (\Throwable $e) {
            Log::warning('Set avatar failed', [
                'user_id'  => (int) $user->id,
                'media_id' => (int) $media->id,
                'error'    => $e->getMessage(),
            ]);

            abort(500, 'Не удалось установить аватар. Смотри laravel.log');
        }
    }
}
