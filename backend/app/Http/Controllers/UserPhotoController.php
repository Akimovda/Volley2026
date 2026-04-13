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

        // Поддержка редактирования админом
        $editingUserId = $request->get('user_id');
        if ($editingUserId && auth()->user()?->isAdmin()) {
            $editingUser = User::find($editingUserId);
            if ($editingUser) {
                $user = $editingUser;
            }
        }

        $photos = $user->getMedia('photos')->sortByDesc('created_at')->values();
        $eventPhotos = $user->getMedia('event_photos')->sortByDesc('created_at')->values();
        $schoolLogos = $user->getMedia('school_logo')->sortByDesc('created_at')->values();
        $schoolCovers = $user->getMedia('school_cover')->sortByDesc('created_at')->values();

        // Есть ли у пользователя школа
        $school    = \App\Models\VolleyballSchool::where('organizer_id', $user->id)->first();
        $hasSchool = $school !== null;
        $mainCoverMediaId = $school?->cover_media_id;

        return view('user.photos', [
            'user'         => $user,
            'photos'       => $photos,
            'eventPhotos'  => $eventPhotos,
            'schoolLogos'  => $schoolLogos,
            'schoolCovers' => $schoolCovers,
            'hasSchool'    => $hasSchool,
            'mainCoverMediaId' => $mainCoverMediaId ?? null,
        ]);
    }

    public function store(Request $request)
    {
        try {
            // 1. Проверка наличия файлов
            if (!$request->hasFile('photo_original') || !$request->hasFile('photo_cropped')) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Не переданы оба файла (оригинал и квадрат)',
                ], 422);
            }

            $originalFile = $request->file('photo_original');
            $croppedFile  = $request->file('photo_cropped');

            // 2. Проверка размера
            if ($originalFile->getSize() > 15 * 1024 * 1024) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Оригинальный файл слишком большой. Максимум 15 МБ',
                ], 422);
            }

            if ($croppedFile->getSize() > 5 * 1024 * 1024) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Квадрат слишком большой. Максимум 5 МБ',
                ], 422);
            }

            // 3. Проверка MIME-типов
            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
            if (!in_array($originalFile->getMimeType(), $allowedMimes)) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Оригинал должен быть в формате JPEG, PNG или WEBP',
                ], 422);
            }

            // 4. Определяем пользователя
            $userId = $request->input('user_id');
            if ($userId && auth()->user()?->isAdmin()) {
                $user = User::find($userId);
                if (!$user) {
                    return response()->json(['success' => false, 'error' => 'Пользователь не найден'], 404);
                }
            } else {
                $user = $request->user();
            }

            // 5. Определяем коллекцию и права
            $photoType  = $request->input('photo_type', 'photos');
            $orgOrAdmin = auth()->user()?->isAdmin() || auth()->user()?->isOrganizer();

            if (in_array($photoType, ['event_photos', 'school_logo', 'school_cover']) && !$orgOrAdmin) {
                return response()->json(['success' => false, 'error' => 'Нет прав для загрузки этого типа фото'], 403);
            }

            $allowedTypes = ['photos', 'event_photos', 'school_logo', 'school_cover'];
            if (!in_array($photoType, $allowedTypes)) {
                return response()->json(['success' => false, 'error' => 'Неверный тип фото'], 422);
            }

            $collection = $photoType;

            // Логотип школы — только 1 фото
            if ($collection === 'school_logo') {
                $existingLogos = $user->getMedia('school_logo');
                if ($existingLogos->count() >= 1) {
                    return response()->json([
                        'success' => false,
                        'error'   => 'Логотип уже загружен. Удалите текущий перед загрузкой нового.',
                    ], 422);
                }
            }

            // 6. Сохраняем оригинал
            $media = $user->addMedia($originalFile)
                ->preservingOriginal()
                ->usingFileName($originalFile->getClientOriginalName())
                ->toMediaCollection($collection);

            // 7. Путь к thumb в зависимости от типа
            $thumbConversion = match($collection) {
                'event_photos' => 'event_thumb',
                'school_logo'  => 'school_logo_thumb',
                'school_cover' => 'school_cover_thumb',
                default        => 'thumb',
            };
            $thumbPath = $media->getPath($thumbConversion);

            if (!$thumbPath) {
                $media->delete();
                return response()->json([
                    'success' => false,
                    'error'   => 'Не удалось получить путь к thumb',
                ], 500);
            }

            if (!file_exists(dirname($thumbPath))) {
                mkdir(dirname($thumbPath), 0755, true);
            }

            file_put_contents($thumbPath, file_get_contents($croppedFile->getRealPath()));

            // 8. Устанавливаем аватар (только для photos)
            $makeAvatar = $request->boolean('make_avatar');
            $hasAvatar  = (bool) $user->avatar_media_id;

            if ($collection === 'photos' && ($makeAvatar || !$hasAvatar)) {
                $user->update(['avatar_media_id' => $media->id]);
            }

            return response()->json([
                'success'  => true,
                'message'  => 'Фото добавлено',
                'media_id' => $media->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Upload error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error'   => 'Ошибка: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function setMainCover(Request $request, Media $media)
    {
        $isOwner = (int) $media->model_id === (int) $request->user()->id;
        $isAdmin = auth()->user()?->isAdmin();

        if (!$isOwner && !$isAdmin) abort(403);

        if ($media->collection_name !== 'school_cover') {
            return back()->with('error', 'Только фотографии школы можно сделать основными ❌');
        }

        $school = \App\Models\VolleyballSchool::where('organizer_id', $media->model_id)->first();
        if (!$school) return back()->with('error', 'Школа не найдена');

        $school->update(['cover_media_id' => $media->id]);

        return back()->with('status', 'Основная фотография обновлена ✅');
    }

    public function setAvatar(Request $request, Media $media)
    {
        $isOwner = (int) $media->model_id === (int) $request->user()->id;
        $isAdmin = auth()->user()?->isAdmin();

        if (!$isOwner && !$isAdmin) {
            abort(403);
        }

        if ($media->collection_name !== 'photos') {
            return back()->with('error', 'Только фото из галереи можно сделать аватаром ❌');
        }

        $user = User::find($media->model_id);
        $user->update(['avatar_media_id' => $media->id]);

        return back()->with('status', 'Аватар обновлён ✅');
    }

    public function destroy(Request $request, Media $media)
    {
        $isOwner = (int) $media->model_id === (int) $request->user()->id;
        $isAdmin = auth()->user()?->isAdmin();

        if (!$isOwner && !$isAdmin) {
            abort(403);
        }

        $user      = User::find($media->model_id);
        $deletedId = $media->id;
        $wasAvatar = ($user->avatar_media_id == $deletedId);

        $media->delete();

        if ($wasAvatar) {
            $fallback = $user->getMedia('photos')->sortByDesc('created_at')->first();
            $user->update(['avatar_media_id' => $fallback?->id]);
        }

        $redirectUrl = $request->user()->isAdmin()
            ? route('user.photos') . '?user_id=' . $user->id
            : back()->getTargetUrl();

        return redirect($redirectUrl)->with('status', 'Фото удалено ✅');
    }

    public function destroyEventPhoto(Request $request, Media $media)
    {
        $isOwner = (int) $media->model_id === (int) $request->user()->id;
        $isAdmin = auth()->user()?->isAdmin() || auth()->user()?->isOrganizer();

        if (!$isOwner && !$isAdmin) {
            abort(403);
        }

        if ($media->collection_name !== 'event_photos') {
            return back()->with('error', 'Это не фото мероприятия ❌');
        }

        $user = User::find($media->model_id);
        $media->delete();

        $redirectUrl = $request->user()->isAdmin()
            ? route('user.photos') . '?user_id=' . $user->id
            : back()->getTargetUrl();

        return redirect($redirectUrl)->with('status', 'Фото мероприятия удалено ✅');
    }
}
