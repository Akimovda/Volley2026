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

        return view('user.photos', [
            'user'   => $user,
            'photos' => $photos,
        ]);
    }

public function store(Request $request)
{
    try {
        // 1. Проверка наличия файлов
        if (!$request->hasFile('photo_original') || !$request->hasFile('photo_cropped')) {
            return response()->json([
                'success' => false,
                'error' => 'Не переданы оба файла (оригинал и квадрат)'
            ], 422);
        }

        $originalFile = $request->file('photo_original');
        $croppedFile = $request->file('photo_cropped');

        // 2. Проверка размера
        $maxSizeOriginal = 15 * 1024 * 1024;
        $maxSizeCropped = 5 * 1024 * 1024;

        if ($originalFile->getSize() > $maxSizeOriginal) {
            return response()->json([
                'success' => false,
                'error' => 'Оригинальный файл слишком большой. Максимум 15 МБ'
            ], 422);
        }

        if ($croppedFile->getSize() > $maxSizeCropped) {
            return response()->json([
                'success' => false,
                'error' => 'Квадрат слишком большой. Максимум 5 МБ'
            ], 422);
        }

        // 3. Проверка MIME-типов
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        
        if (!in_array($originalFile->getMimeType(), $allowedMimes)) {
            return response()->json([
                'success' => false,
                'error' => 'Оригинал должен быть в формате JPEG, PNG или WEBP. Получен: ' . $originalFile->getMimeType()
            ], 422);
        }
        
        if (!in_array($croppedFile->getMimeType(), $allowedMimes)) {
            return response()->json([
                'success' => false,
                'error' => 'Квадрат должен быть в формате JPEG, PNG или WEBP. Получен: ' . $croppedFile->getMimeType()
            ], 422);
        }

        /** @var User $user */
        $user = $request->user();

        // 4. Сохраняем оригинал
        try {
            $media = $user->addMedia($originalFile)
                ->preservingOriginal()
                ->usingFileName($originalFile->getClientOriginalName())
                ->toMediaCollection('photos');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Ошибка сохранения оригинала: ' . $e->getMessage()
            ], 500);
        }

        // Проверяем, что медиа создалось
        if (!$media || !$media->exists()) {
            return response()->json([
                'success' => false,
                'error' => 'Медиа не создано или не существует'
            ], 500);
        }

        // 5. Сохраняем thumb
        $thumbPath = $media->getPath('thumb');
        
        if (!$thumbPath) {
            $media->delete();
            return response()->json([
                'success' => false,
                'error' => 'Не удалось получить путь к thumb (конверсия не объявлена в модели)'
            ], 500);
        }

        try {
            if (!file_exists(dirname($thumbPath))) {
                if (!mkdir(dirname($thumbPath), 0755, true)) {
                    throw new \Exception('Не удалось создать директорию для thumb');
                }
            }
            
            $written = file_put_contents($thumbPath, file_get_contents($croppedFile->getRealPath()));
            
            if ($written === false) {
                throw new \Exception('Не удалось записать thumb (ошибка записи)');
            }
            
            if ($written == 0) {
                throw new \Exception('Записано 0 байт в thumb');
            }
            
            if (!file_exists($thumbPath)) {
                throw new \Exception('Thumb не появился на диске после записи');
            }
        } catch (\Exception $e) {
            $media->delete();
            return response()->json([
                'success' => false,
                'error' => 'Ошибка сохранения квадрата: ' . $e->getMessage()
            ], 500);
        }

        // 6. Устанавливаем аватар
        $makeAvatar = $request->boolean('make_avatar');
        $hasAvatar = (bool) $user->avatar_media_id;
        
        if ($makeAvatar || !$hasAvatar) {
            $user->update(['avatar_media_id' => $media->id]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Фото добавлено',
            'media_id' => $media->id
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => 'Неожиданная ошибка: ' . $e->getMessage()
        ], 500);
    }
}
    public function setAvatar(Request $request, Media $media)
    {
        /** @var User $user */
        $user = $request->user();

        $this->assertOwnedByUser($media, $user);

        // Просто сохраняем ID фото как аватар
        $user->update([
            'avatar_media_id' => $media->id,
        ]);

        return back()->with('status', 'Аватар обновлён ✅');
    }

    public function destroy(Request $request, Media $media)
    {
        /** @var User $user */
        $user = $request->user();

        $this->assertOwnedByUser($media, $user);

        $deletedId = $media->id;
        $wasAvatar = ($user->avatar_media_id == $deletedId);

        $media->delete();

        // Если удалили фото, которое было аватаром
        if ($wasAvatar) {
            $fallback = $user->getMedia('photos')->sortByDesc('created_at')->first();
            if ($fallback) {
                $user->update(['avatar_media_id' => $fallback->id]);
            } else {
                $user->update(['avatar_media_id' => null]);
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
}